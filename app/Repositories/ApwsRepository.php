<?php

namespace App\Repositories;

use App\Models\ApwsCreative;
use App\Models\ApwsCreativeAdvertiser;
use App\Models\ApwsCreativeProduct;
use App\Models\ApwsCustomerCredential;
use App\Models\ApwsPlacement;
use App\Models\ApwsSyncCursor;
use App\Models\ApwsSyncRun;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApwsRepository
{
    public function upsertCustomerCredential(string $customerUuid, string $providerCod): ApwsCustomerCredential
    {
        return ApwsCustomerCredential::query()->updateOrCreate(
            ['customer_uuid' => $customerUuid],
            ['provider_cod' => $providerCod]
        );
    }

    public function getCustomerProviderCod(string $customerUuid): ?string
    {
        $credential = ApwsCustomerCredential::query()
            ->where('customer_uuid', $customerUuid)
            ->first();

        if (!$credential) {
            return null;
        }

        try {
            $value = trim((string) $credential->provider_cod);
        } catch (\Throwable) {
            return null;
        }

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    public function listCredentialCustomerUuids(): array
    {
        return ApwsCustomerCredential::query()
            ->select('customer_uuid')
            ->distinct()
            ->pluck('customer_uuid')
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    public function createSyncRun(array $data): ApwsSyncRun
    {
        return ApwsSyncRun::create($data);
    }

    public function listSyncRuns(string $customerUuid, int $perPage = 20): LengthAwarePaginator
    {
        return ApwsSyncRun::query()
            ->where('customer_uuid', $customerUuid)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getCursor(string $sourceKey = 'apws', string $customerUuid = ''): ?ApwsSyncCursor
    {
        $query = ApwsSyncCursor::query()->where('source_key', $sourceKey);
        if ($customerUuid !== '') {
            $query->where('customer_uuid', $customerUuid);
        }

        return $query->first();
    }

    public function upsertCursor(string $sourceKey, string $customerUuid, string $lastSuccessT2): ApwsSyncCursor
    {
        return ApwsSyncCursor::query()->updateOrCreate(
            ['source_key' => $sourceKey, 'customer_uuid' => $customerUuid],
            ['last_success_t2' => $lastSuccessT2, 'customer_uuid' => $customerUuid]
        );
    }

    public function upsertCreative(string $customerUuid, array $payload, bool $persistRaw = true): ApwsCreative
    {
        $creativeCode = trim((string) ($payload['criativo'] ?? ''));
        if ($creativeCode === '') {
            throw new \InvalidArgumentException('Missing creative code in payload.');
        }

        $coleta = is_array($payload['coleta'] ?? null) ? $payload['coleta'] : [];
        [$collectCity, $collectState] = $this->splitCityState((string) ($coleta['cidade'] ?? ''));

        $download = Arr::first((array) ($payload['downloads'] ?? []));
        $primaryFileType = is_string($download) && str_contains($download, '.')
            ? strtolower((string) pathinfo($download, PATHINFO_EXTENSION))
            : null;

        $creative = ApwsCreative::query()->updateOrCreate(
            ['customer_uuid' => $customerUuid, 'creative_code' => $creativeCode],
            [
                'customer_uuid' => $customerUuid,
                'campaign_code' => $this->nullableString($payload['campanha'] ?? null),
                'media_type' => $this->nullableString($payload['midia'] ?? null),
                'collect_date' => $this->parseDate((string) ($coleta['data'] ?? '')),
                'collect_city' => $collectCity,
                'collect_state' => $collectState,
                'collect_vehicle' => $this->nullableString($coleta['veiculo'] ?? null),
                'primary_file_type' => $primaryFileType,
                'raw_payload' => $persistRaw ? $payload : null,
            ]
        );

        $this->syncAdvertisers($creative->id, (array) ($payload['anunciantes'] ?? []));
        $this->syncProducts($creative->id, (array) ($payload['produtos'] ?? []));

        return $creative;
    }

    public function upsertPlacement(string $customerUuid, array $payload, bool $persistRaw = true): ApwsPlacement
    {
        $rawExternalId = $payload['id'] ?? null;
        $externalId = is_scalar($rawExternalId) ? trim((string) $rawExternalId) : '';

        if ($externalId === '') {
            $externalId = sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: uniqid('placement-', true));
        }

        $placeRaw = (string) ($payload['praca'] ?? ($payload['cidade'] ?? ''));
        [$city, $state] = $this->splitCityState($placeRaw);

        return ApwsPlacement::query()->updateOrCreate(
            ['customer_uuid' => $customerUuid, 'placement_external_id' => $externalId],
            [
                'customer_uuid' => $customerUuid,
                'creative_code' => trim((string) ($payload['criativo'] ?? '')),
                'aired_at' => $this->parseDateTime((string) ($payload['data'] ?? '')),
                'place_raw' => $this->nullableString($placeRaw),
                'city' => $city,
                'state' => $state,
                'vehicle' => $this->nullableString($payload['veiculo'] ?? null),
                'raw_payload' => $persistRaw ? $payload : null,
            ]
        );
    }

    public function findCampaignByCode(string $customerUuid, string $creativeCode): ?ApwsCreative
    {
        return ApwsCreative::query()
            ->with([
                'advertisers',
                'products',
                'placements' => static function ($query) use ($customerUuid): void {
                    $query->where('customer_uuid', $customerUuid);
                },
            ])
            ->where('customer_uuid', $customerUuid)
            ->where('creative_code', $creativeCode)
            ->first();
    }

    public function paginateCampaigns(
        array $filters,
        int $page,
        int $perPage,
        string $sortBy,
        string $sortOrder
    ): LengthAwarePaginator {
        $customerUuid = trim((string) ($filters['customer_uuid'] ?? ''));
        $query = $this->baseCreativeQuery($filters)
            ->with(['advertisers', 'products'])
            ->withCount([
                'placements as placements_count' => static function ($query) use ($customerUuid): void {
                    if ($customerUuid !== '') {
                        $query->where('customer_uuid', $customerUuid);
                    }
                },
            ]);

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getSummary(array $filters): array
    {
        $creativeQuery = $this->baseCreativeQuery($filters);

        $totalCampaigns = (clone $creativeQuery)->count();
        $totalBrands = (clone $creativeQuery)
            ->join('apws_creative_advertisers as a', 'a.creative_id', '=', 'apws_creatives.id')
            ->distinct('a.advertiser')
            ->count('a.advertiser');
        $totalProducts = (clone $creativeQuery)
            ->join('apws_creative_products as p', 'p.creative_id', '=', 'apws_creatives.id')
            ->distinct('p.product')
            ->count('p.product');

        $codesSubquery = $this->baseCreativeQuery($filters)->select('apws_creatives.creative_code');
        $customerUuid = (string) ($filters['customer_uuid'] ?? '');

        $totalPlacements = ApwsPlacement::query()
            ->where('customer_uuid', $customerUuid)
            ->whereIn('creative_code', $codesSubquery)
            ->count();

        return [
            'total_campaigns' => $totalCampaigns,
            'total_placements' => $totalPlacements,
            'total_brands' => $totalBrands,
            'total_products' => $totalProducts,
        ];
    }

    public function aggregateBrands(
        array $filters,
        int $limit = 20,
        string $sortBy = 'campaigns',
        string $sortOrder = 'desc'
    ): array
    {
        return $this->aggregateManyToMany(
            $filters,
            'apws_creative_advertisers',
            'advertiser',
            $limit,
            $sortBy,
            $sortOrder
        );
    }

    public function aggregateProducts(
        array $filters,
        int $limit = 20,
        string $sortBy = 'campaigns',
        string $sortOrder = 'desc'
    ): array
    {
        return $this->aggregateManyToMany(
            $filters,
            'apws_creative_products',
            'product',
            $limit,
            $sortBy,
            $sortOrder
        );
    }

    public function aggregateMedia(
        array $filters,
        int $limit = 20,
        string $sortBy = 'campaigns',
        string $sortOrder = 'desc'
    ): array
    {
        $safeSortBy = $this->sanitizeAggregateSortBy($sortBy);
        $safeSortOrder = $this->sanitizeSortOrder($sortOrder, 'desc');

        $rows = $this->baseCreativeQuery($filters)
            ->leftJoin('apws_placements as pl', function ($join): void {
                $join
                    ->on('pl.creative_code', '=', 'apws_creatives.creative_code')
                    ->on('pl.customer_uuid', '=', 'apws_creatives.customer_uuid');
            })
            ->selectRaw("COALESCE(apws_creatives.media_type, 'N/D') as label")
            ->selectRaw('COUNT(DISTINCT apws_creatives.id) as campaigns')
            ->selectRaw('COUNT(DISTINCT pl.id) as placements')
            ->groupByRaw("COALESCE(apws_creatives.media_type, 'N/D')")
            ->orderBy($safeSortBy, $safeSortOrder)
            ->orderBy('label')
            ->limit($limit)
            ->get();

        return $rows->map(static fn ($row) => [
            'label' => (string) $row->label,
            'campaigns' => (int) $row->campaigns,
            'placements' => (int) $row->placements,
        ])->all();
    }

    public function aggregateRegions(
        array $filters,
        int $limit = 20,
        string $sortBy = 'campaigns',
        string $sortOrder = 'desc'
    ): array
    {
        $safeSortBy = $this->sanitizeAggregateSortBy($sortBy);
        $safeSortOrder = $this->sanitizeSortOrder($sortOrder, 'desc');

        $stateRows = $this->baseCreativeQuery($filters)
            ->leftJoin('apws_placements as pl', function ($join): void {
                $join
                    ->on('pl.creative_code', '=', 'apws_creatives.creative_code')
                    ->on('pl.customer_uuid', '=', 'apws_creatives.customer_uuid');
            })
            ->selectRaw("COALESCE(apws_creatives.collect_state, 'N/D') as state")
            ->selectRaw('COUNT(DISTINCT apws_creatives.id) as campaigns')
            ->selectRaw('COUNT(DISTINCT pl.id) as placements')
            ->groupByRaw("COALESCE(apws_creatives.collect_state, 'N/D')")
            ->get();

        $byMacro = [];
        foreach ($stateRows as $row) {
            $state = strtoupper((string) $row->state);
            $macro = $this->macroRegionFromState($state);
            $key = $macro === null ? $state : $macro;

            if (!isset($byMacro[$key])) {
                $byMacro[$key] = [
                    'label' => $key,
                    'campaigns' => 0,
                    'placements' => 0,
                    'states' => [],
                ];
            }

            $byMacro[$key]['campaigns'] += (int) $row->campaigns;
            $byMacro[$key]['placements'] += (int) $row->placements;
            if ($state !== '' && $state !== 'N/D') {
                $byMacro[$key]['states'][$state] = true;
            }
        }

        $rows = array_values(array_map(static function (array $item): array {
            $item['states'] = array_values(array_keys($item['states']));
            sort($item['states']);
            return $item;
        }, $byMacro));

        usort($rows, function (array $a, array $b) use ($safeSortBy, $safeSortOrder): int {
            return $this->compareRegionRows($a, $b, $safeSortBy, $safeSortOrder);
        });

        return array_slice($rows, 0, $limit);
    }

    public function timeline(
        array $filters,
        string $groupBy,
        string $dimension,
        string $sortBy = 'period',
        string $sortOrder = 'asc'
    ): array
    {
        $safeSortBy = $this->sanitizeTimelineSortBy($sortBy);
        $safeSortOrder = $this->sanitizeSortOrder($sortOrder, 'asc');
        $format = $groupBy === 'day' ? 'Y-m-d' : 'Y-m';

        $query = $this->timelineQueryByDimension($filters, $dimension)
            ->whereNotNull('apws_creatives.collect_date')
            ->get();

        $bucket = [];
        foreach ($query as $row) {
            $date = $row->collect_date;
            if ($date === null) {
                continue;
            }
            $period = Carbon::parse($date)->format($format);
            $label = (string) ($row->label ?? 'N/D');
            $creativeCode = (string) ($row->creative_code ?? '');

            if ($creativeCode === '') {
                continue;
            }

            $bucket[$period][$label][$creativeCode] = true;
        }

        ksort($bucket);

        $result = [];
        foreach ($bucket as $period => $labels) {
            $items = [];
            foreach ($labels as $label => $creativeSet) {
                $items[] = [
                    'label' => $label,
                    'campaigns' => count($creativeSet),
                ];
            }

            usort($items, static fn (array $a, array $b): int => $b['campaigns'] <=> $a['campaigns']);
            $topLabel = (string) ($items[0]['label'] ?? 'N/D');

            $result[] = [
                'period' => $period,
                'items' => $items,
                'total_campaigns' => array_sum(array_map(static fn (array $row) => $row['campaigns'], $items)),
                'top_label' => $topLabel,
            ];
        }

        usort($result, function (array $a, array $b) use ($safeSortBy, $safeSortOrder): int {
            return $this->compareTimelineRows($a, $b, $safeSortBy, $safeSortOrder);
        });

        return $result;
    }

    public function filterOptions(array $filters = []): array
    {
        $query = $this->baseCreativeQuery($filters);

        $advertisers = (clone $query)
            ->join('apws_creative_advertisers as a', 'a.creative_id', '=', 'apws_creatives.id')
            ->distinct()
            ->orderBy('a.advertiser')
            ->pluck('a.advertiser')
            ->filter()
            ->values()
            ->all();

        $products = (clone $query)
            ->join('apws_creative_products as p', 'p.creative_id', '=', 'apws_creatives.id')
            ->distinct()
            ->orderBy('p.product')
            ->pluck('p.product')
            ->filter()
            ->values()
            ->all();

        $media = (clone $query)
            ->distinct()
            ->orderBy('apws_creatives.media_type')
            ->pluck('apws_creatives.media_type')
            ->filter()
            ->values()
            ->all();

        $states = (clone $query)
            ->distinct()
            ->orderBy('apws_creatives.collect_state')
            ->pluck('apws_creatives.collect_state')
            ->filter()
            ->map(static fn ($state) => strtoupper((string) $state))
            ->values()
            ->all();

        $regions = collect($states)
            ->map(fn (string $state) => $this->macroRegionFromState($state))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $vehicles = (clone $query)
            ->distinct()
            ->orderBy('apws_creatives.collect_vehicle')
            ->pluck('apws_creatives.collect_vehicle')
            ->filter()
            ->values()
            ->all();

        $range = (clone $query)
            ->selectRaw('MIN(apws_creatives.collect_date) as min_date, MAX(apws_creatives.collect_date) as max_date')
            ->first();

        return [
            'advertisers' => $advertisers,
            'products' => $products,
            'media' => $media,
            'states' => $states,
            'regions' => $regions,
            'vehicles' => $vehicles,
            'date_range' => [
                'start_date' => $range?->min_date,
                'end_date' => $range?->max_date,
            ],
        ];
    }

    public function countCampaigns(?string $customerUuid = null): int
    {
        $query = ApwsCreative::query();
        if (is_string($customerUuid) && $customerUuid !== '') {
            $query->where('customer_uuid', $customerUuid);
        }

        return $query->count();
    }

    public function countPlacements(?string $customerUuid = null): int
    {
        $query = ApwsPlacement::query();
        if (is_string($customerUuid) && $customerUuid !== '') {
            $query->where('customer_uuid', $customerUuid);
        }

        return $query->count();
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitCityState(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [null, null];
        }

        if (!str_contains($trimmed, '/')) {
            return [$trimmed, null];
        }

        $parts = explode('/', $trimmed);
        $state = strtoupper(trim((string) array_pop($parts)));
        $city = trim(implode('/', $parts));

        return [$city !== '' ? $city : null, $state !== '' ? $state : null];
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y - H:i', 'd/m/Y H:i'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateTimeString();
            } catch (\Throwable) {
                // continue
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function syncAdvertisers(int $creativeId, array $advertisers): void
    {
        ApwsCreativeAdvertiser::query()->where('creative_id', $creativeId)->delete();

        $rows = collect($advertisers)
            ->filter(static fn ($value) => is_scalar($value))
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->map(static fn (string $value) => [
                'creative_id' => $creativeId,
                'advertiser' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            ApwsCreativeAdvertiser::query()->insert($rows);
        }
    }

    private function syncProducts(int $creativeId, array $products): void
    {
        ApwsCreativeProduct::query()->where('creative_id', $creativeId)->delete();

        $rows = collect($products)
            ->filter(static fn ($value) => is_scalar($value))
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->map(static fn (string $value) => [
                'creative_id' => $creativeId,
                'product' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            ApwsCreativeProduct::query()->insert($rows);
        }
    }

    private function baseCreativeQuery(array $filters): Builder
    {
        $query = ApwsCreative::query();
        $customerUuid = trim((string) ($filters['customer_uuid'] ?? ''));
        if ($customerUuid === '') {
            // Fail-safe for tenant isolation: without customer scope, return no rows.
            return $query->whereRaw('1 = 0');
        }

        $query->where('apws_creatives.customer_uuid', $customerUuid);

        if (!empty($filters['start_date'])) {
            $query->whereDate('apws_creatives.collect_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('apws_creatives.collect_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['media'])) {
            $query->whereIn('apws_creatives.media_type', array_values($filters['media']));
        }

        if (!empty($filters['campaign_code'])) {
            $query->where('apws_creatives.campaign_code', $filters['campaign_code']);
        }

        if (!empty($filters['vehicles'])) {
            $query->whereIn('apws_creatives.collect_vehicle', array_values($filters['vehicles']));
        }

        if (!empty($filters['advertisers'])) {
            $values = array_values($filters['advertisers']);
            $query->whereExists(static function ($sub) use ($values): void {
                $sub->selectRaw('1')
                    ->from('apws_creative_advertisers')
                    ->whereColumn('apws_creative_advertisers.creative_id', 'apws_creatives.id')
                    ->whereIn('advertiser', $values);
            });
        }

        if (!empty($filters['products'])) {
            $values = array_values($filters['products']);
            $query->whereExists(static function ($sub) use ($values): void {
                $sub->selectRaw('1')
                    ->from('apws_creative_products')
                    ->whereColumn('apws_creative_products.creative_id', 'apws_creatives.id')
                    ->whereIn('product', $values);
            });
        }

        if (!empty($filters['regions'])) {
            $states = $this->expandRegionFilters((array) $filters['regions']);
            if (!empty($states)) {
                $query->whereIn('apws_creatives.collect_state', $states);
            }
        }

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            if ($term !== '') {
                $query->where(static function (Builder $builder) use ($term): void {
                    $like = '%' . str_replace('%', '\\%', $term) . '%';
                    $builder
                        ->where('apws_creatives.creative_code', 'like', $like)
                        ->orWhere('apws_creatives.campaign_code', 'like', $like)
                        ->orWhere('apws_creatives.collect_vehicle', 'like', $like)
                        ->orWhereExists(static function ($sub) use ($like): void {
                            $sub->selectRaw('1')
                                ->from('apws_creative_advertisers')
                                ->whereColumn('apws_creative_advertisers.creative_id', 'apws_creatives.id')
                                ->where('advertiser', 'like', $like);
                        })
                        ->orWhereExists(static function ($sub) use ($like): void {
                            $sub->selectRaw('1')
                                ->from('apws_creative_products')
                                ->whereColumn('apws_creative_products.creative_id', 'apws_creatives.id')
                                ->where('product', 'like', $like);
                        });
                });
            }
        }

        return $query;
    }

    private function timelineQueryByDimension(array $filters, string $dimension): Builder
    {
        $query = $this->baseCreativeQuery($filters);

        return match ($dimension) {
            'products' => $query
                ->join('apws_creative_products as x', 'x.creative_id', '=', 'apws_creatives.id')
                ->select(['apws_creatives.collect_date', 'apws_creatives.creative_code', DB::raw('x.product as label')]),
            'regions' => $query
                ->select([
                    'apws_creatives.collect_date',
                    'apws_creatives.creative_code',
                    DB::raw("COALESCE(apws_creatives.collect_state, 'N/D') as label"),
                ]),
            'media' => $query
                ->select([
                    'apws_creatives.collect_date',
                    'apws_creatives.creative_code',
                    DB::raw("COALESCE(apws_creatives.media_type, 'N/D') as label"),
                ]),
            default => $query
                ->join('apws_creative_advertisers as x', 'x.creative_id', '=', 'apws_creatives.id')
                ->select(['apws_creatives.collect_date', 'apws_creatives.creative_code', DB::raw('x.advertiser as label')]),
        };
    }

    private function aggregateManyToMany(
        array $filters,
        string $table,
        string $column,
        int $limit,
        string $sortBy = 'campaigns',
        string $sortOrder = 'desc'
    ): array
    {
        $safeSortBy = $this->sanitizeAggregateSortBy($sortBy);
        $safeSortOrder = $this->sanitizeSortOrder($sortOrder, 'desc');

        $rows = $this->baseCreativeQuery($filters)
            ->join("{$table} as x", 'x.creative_id', '=', 'apws_creatives.id')
            ->leftJoin('apws_placements as pl', function ($join): void {
                $join
                    ->on('pl.creative_code', '=', 'apws_creatives.creative_code')
                    ->on('pl.customer_uuid', '=', 'apws_creatives.customer_uuid');
            })
            ->selectRaw("x.{$column} as label")
            ->selectRaw('COUNT(DISTINCT apws_creatives.id) as campaigns')
            ->selectRaw('COUNT(DISTINCT pl.id) as placements')
            ->groupByRaw("x.{$column}")
            ->orderBy($safeSortBy, $safeSortOrder)
            ->orderBy('label')
            ->limit($limit)
            ->get();

        return $rows->map(static fn ($row) => [
            'label' => (string) $row->label,
            'campaigns' => (int) $row->campaigns,
            'placements' => (int) $row->placements,
        ])->all();
    }

    private function sanitizeAggregateSortBy(string $sortBy): string
    {
        $normalized = strtolower(trim($sortBy));

        return in_array($normalized, ['label', 'campaigns', 'placements'], true)
            ? $normalized
            : 'campaigns';
    }

    private function sanitizeTimelineSortBy(string $sortBy): string
    {
        $normalized = strtolower(trim($sortBy));

        return in_array($normalized, ['period', 'total_campaigns', 'top_label'], true)
            ? $normalized
            : 'period';
    }

    private function sanitizeSortOrder(string $sortOrder, string $default = 'desc'): string
    {
        $normalized = strtolower(trim($sortOrder));

        if (in_array($normalized, ['asc', 'desc'], true)) {
            return $normalized;
        }

        return strtolower($default) === 'asc' ? 'asc' : 'desc';
    }

    private function compareRegionRows(array $a, array $b, string $sortBy, string $sortOrder): int
    {
        $direction = $sortOrder === 'asc' ? 1 : -1;

        if ($sortBy === 'label') {
            $labelCompare = $this->compareStrings((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
            if ($labelCompare !== 0) {
                return $labelCompare * $direction;
            }

            return ((int) ($a['campaigns'] ?? 0) <=> (int) ($b['campaigns'] ?? 0)) * -1;
        }

        if ($sortBy === 'placements') {
            $placementCompare = ((int) ($a['placements'] ?? 0) <=> (int) ($b['placements'] ?? 0)) * $direction;
            if ($placementCompare !== 0) {
                return $placementCompare;
            }

            return $this->compareStrings((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        }

        $campaignCompare = ((int) ($a['campaigns'] ?? 0) <=> (int) ($b['campaigns'] ?? 0)) * $direction;
        if ($campaignCompare !== 0) {
            return $campaignCompare;
        }

        return $this->compareStrings((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    }

    private function compareTimelineRows(array $a, array $b, string $sortBy, string $sortOrder): int
    {
        $direction = $sortOrder === 'asc' ? 1 : -1;

        if ($sortBy === 'period') {
            $periodCompare = $this->compareStrings((string) ($a['period'] ?? ''), (string) ($b['period'] ?? ''));
            if ($periodCompare !== 0) {
                return $periodCompare * $direction;
            }

            return ((int) ($a['total_campaigns'] ?? 0) <=> (int) ($b['total_campaigns'] ?? 0)) * -1;
        }

        if ($sortBy === 'top_label') {
            $labelCompare = $this->compareStrings((string) ($a['top_label'] ?? ''), (string) ($b['top_label'] ?? ''));
            if ($labelCompare !== 0) {
                return $labelCompare * $direction;
            }

            return $this->compareStrings((string) ($a['period'] ?? ''), (string) ($b['period'] ?? ''));
        }

        $campaignCompare = ((int) ($a['total_campaigns'] ?? 0) <=> (int) ($b['total_campaigns'] ?? 0)) * $direction;
        if ($campaignCompare !== 0) {
            return $campaignCompare;
        }

        return $this->compareStrings((string) ($a['period'] ?? ''), (string) ($b['period'] ?? ''));
    }

    private function compareStrings(string $left, string $right): int
    {
        return strcasecmp($left, $right);
    }

    /**
     * @param array<int, string> $regions
     * @return array<int, string>
     */
    private function expandRegionFilters(array $regions): array
    {
        $states = [];

        foreach ($regions as $region) {
            $raw = strtoupper(trim($region));
            if ($raw === '') {
                continue;
            }

            if (strlen($raw) === 2) {
                $states[$raw] = true;
                continue;
            }

            $normalized = Str::of($raw)
                ->ascii()
                ->replace(' ', '-')
                ->replace('_', '-')
                ->lower()
                ->toString();

            foreach ($this->macroRegionMap() as $macro => $ufs) {
                if ($normalized === $macro) {
                    foreach ($ufs as $uf) {
                        $states[$uf] = true;
                    }
                }
            }
        }

        return array_values(array_keys($states));
    }

    private function macroRegionFromState(string $state): ?string
    {
        foreach ($this->macroRegionMap() as $macro => $states) {
            if (in_array($state, $states, true)) {
                return $macro;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function macroRegionMap(): array
    {
        return [
            'norte' => ['AC', 'AP', 'AM', 'PA', 'RO', 'RR', 'TO'],
            'nordeste' => ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE'],
            'centro-oeste' => ['DF', 'GO', 'MT', 'MS'],
            'sudeste' => ['ES', 'MG', 'RJ', 'SP'],
            'sul' => ['PR', 'RS', 'SC'],
        ];
    }
}

<?php

namespace App\Http\Services;

use App\Models\ApwsSyncRun;
use App\Repositories\ApwsRepository;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ApwsScheduledSyncService
{
    public function __construct(
        private readonly ApwsSyncService $syncService,
        private readonly ApwsRepository $repository,
    ) {
    }

    /**
     * @return array{0:string,1:string}
     */
    public function resolveWindow(string $customerUuid, ?string $t1 = null, ?string $t2 = null): array
    {
        $windowMinutes = max(1, (int) config('services.apws.sync.window_minutes', 60));
        $maxWindowDays = max(1, (int) config('services.apws.sync.max_window_days', 30));

        $resolvedT2 = $this->resolveEndTimestamp($t2);
        $resolvedT1 = $this->resolveStartTimestamp($customerUuid, $t1, $resolvedT2, $windowMinutes);

        if ($resolvedT1->greaterThan($resolvedT2)) {
            if (is_string($t1) && trim($t1) !== '') {
                throw new InvalidArgumentException('t1 must be less than or equal to t2.');
            }

            $resolvedT1 = $resolvedT2->subMinutes($windowMinutes);
        }

        $maxEndByProviderWindow = $resolvedT1->addDays($maxWindowDays);
        if ($resolvedT2->greaterThan($maxEndByProviderWindow)) {
            $resolvedT2 = $maxEndByProviderWindow;
        }

        return [
            $resolvedT1->format('Ymd\THis'),
            $resolvedT2->format('Ymd\THis'),
        ];
    }

    public function run(string $customerUuid, ?string $t1 = null, ?string $t2 = null, ?bool $persistRaw = null): ApwsSyncRun
    {
        $resolvedCustomerUuid = $this->resolveCustomerUuid($customerUuid);
        [$resolvedT1, $resolvedT2] = $this->resolveWindow($resolvedCustomerUuid, $t1, $t2);

        $shouldPersistRaw = $persistRaw ?? (bool) config('services.apws.sync.persist_raw', true);

        return $this->syncService->run($resolvedCustomerUuid, $resolvedT1, $resolvedT2, $shouldPersistRaw);
    }

    private function resolveStartTimestamp(string $customerUuid, ?string $t1, CarbonImmutable $resolvedT2, int $windowMinutes): CarbonImmutable
    {
        if (is_string($t1) && trim($t1) !== '') {
            return $this->parseTimestamp($t1, 't1');
        }

        $cursorT1 = $this->tryParseTimestamp($this->repository->getCursor('apws', $customerUuid)?->last_success_t2);
        if ($cursorT1 !== null) {
            return $cursorT1;
        }

        $initialT1 = trim((string) config('services.apws.sync.initial_t1', ''));
        $configuredInitial = $this->tryParseTimestamp($initialT1);
        if ($configuredInitial !== null) {
            return $configuredInitial;
        }

        return $resolvedT2->subMinutes($windowMinutes);
    }

    private function resolveEndTimestamp(?string $t2): CarbonImmutable
    {
        if (is_string($t2) && trim($t2) !== '') {
            return $this->parseTimestamp($t2, 't2');
        }

        return CarbonImmutable::now($this->timezone());
    }

    private function parseTimestamp(string $value, string $fieldName): CarbonImmutable
    {
        $normalized = trim($value);

        if (!preg_match('/^\d{8}T\d{6}$/', $normalized)) {
            throw new InvalidArgumentException("Invalid {$fieldName} format. Expected Ymd\\THis.");
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Ymd\THis', $normalized, $this->timezone());
        } catch (\Throwable) {
            $parsed = false;
        }

        if (!$parsed || $parsed->format('Ymd\THis') !== $normalized) {
            throw new InvalidArgumentException("Invalid {$fieldName} value.");
        }

        return $parsed;
    }

    private function tryParseTimestamp(?string $value): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return $this->parseTimestamp($value, 'cursor');
        } catch (\Throwable) {
            return null;
        }
    }

    private function timezone(): string
    {
        return (string) config('services.apws.sync.timezone', 'America/Sao_Paulo');
    }

    private function resolveCustomerUuid(string $customerUuid): string
    {
        $normalized = trim($customerUuid);
        if ($normalized !== '') {
            return $normalized;
        }

        $fallback = trim((string) config('services.apws.default_customer_uuid', ''));
        if ($fallback === '') {
            throw new InvalidArgumentException('customer_uuid is required to run APWS sync.');
        }

        return $fallback;
    }
}

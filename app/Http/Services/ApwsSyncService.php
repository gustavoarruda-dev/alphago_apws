<?php

namespace App\Http\Services;

use App\Models\ApwsSyncRun;
use App\Repositories\ApwsRepository;
use Illuminate\Support\Facades\DB;

class ApwsSyncService
{
    public function __construct(
        private readonly ApwsApiClient $client,
        private readonly ApwsRepository $repository,
        private readonly ApwsMediaStorageService $mediaStorageService,
    ) {
    }

    public function run(string $customerUuid, ?string $t1, ?string $t2, bool $persistRaw = true): ApwsSyncRun
    {
        $startedAt = microtime(true);

        try {
            $provider = $this->client->fetch($customerUuid, $t1, $t2);
            $payload = $provider['payload'];
            $httpStatus = (int) $provider['http_status'];

            $mensagens = is_array($payload['mensagens'] ?? null) ? $payload['mensagens'] : [];
            $providerMessage = trim((string) ($mensagens['mensagem'] ?? ''));

            $status = $providerMessage === '' ? 'success' : 'partial';
            if (str_contains(mb_strtolower($providerMessage), 'erro')) {
                $status = 'failed';
            }

            $preparedCreatives = [];
            foreach ((array) ($payload['criativos'] ?? []) as $creative) {
                if (!is_array($creative)) {
                    continue;
                }

                $mediaMeta = null;
                try {
                    $mediaMeta = $this->mediaStorageService->resolveAndStore($customerUuid, $creative);
                } catch (\Throwable) {
                    // Media persistence is best-effort and must not block metadata sync.
                }

                $preparedCreatives[] = [
                    'payload' => $creative,
                    'media_meta' => $mediaMeta,
                ];
            }

            $run = DB::transaction(function () use ($preparedCreatives, $payload, $mensagens, $httpStatus, $status, $providerMessage, $persistRaw, $startedAt, $t1, $t2, $customerUuid): ApwsSyncRun {
                foreach ($preparedCreatives as $entry) {
                    $this->repository->upsertCreative(
                        $customerUuid,
                        (array) ($entry['payload'] ?? []),
                        $persistRaw,
                        is_array($entry['media_meta'] ?? null) ? $entry['media_meta'] : null
                    );
                }

                foreach ((array) ($payload['veiculacoes'] ?? []) as $placement) {
                    if (is_array($placement)) {
                        $this->repository->upsertPlacement($customerUuid, $placement, $persistRaw);
                    }
                }

                $run = $this->repository->createSyncRun([
                    'customer_uuid' => $customerUuid,
                    'request_t1' => $t1,
                    'request_t2' => $t2,
                    'response_t1' => $mensagens['TempoInicio'] ?? null,
                    'response_t2' => $mensagens['TempoFim'] ?? null,
                    'status' => $status,
                    'http_status' => $httpStatus,
                    'provider_message' => $providerMessage !== '' ? $providerMessage : null,
                    'error_message' => null,
                    'quantity_creatives' => (int) ($mensagens['quantidade_criativos'] ?? count((array) ($payload['criativos'] ?? []))),
                    'quantity_placements' => (int) ($mensagens['quantidade_veiculacoes'] ?? count((array) ($payload['veiculacoes'] ?? []))),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'raw_payload' => $persistRaw ? $payload : null,
                ]);

                $responseT2 = trim((string) ($mensagens['TempoFim'] ?? ''));
                if ($status === 'success' && $responseT2 !== '') {
                    $this->repository->upsertCursor('apws', $customerUuid, $responseT2);
                }

                return $run;
            });

            return $run;
        } catch (\Throwable $exception) {
            return $this->repository->createSyncRun([
                'customer_uuid' => $customerUuid,
                'request_t1' => $t1,
                'request_t2' => $t2,
                'response_t1' => null,
                'response_t2' => null,
                'status' => 'failed',
                'http_status' => method_exists($exception, 'statusCode') ? (int) $exception->statusCode() : 500,
                'provider_message' => null,
                'error_message' => $exception->getMessage(),
                'quantity_creatives' => 0,
                'quantity_placements' => 0,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'raw_payload' => null,
            ]);
        }
    }
}

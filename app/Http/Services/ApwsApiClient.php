<?php

namespace App\Http\Services;

use App\Exceptions\ApwsApiException;
use App\Repositories\ApwsRepository;
use Illuminate\Support\Facades\Http;

class ApwsApiClient
{
    public function __construct(private readonly ApwsRepository $repository)
    {
    }

    public function fetch(string $customerUuid, ?string $t1 = null, ?string $t2 = null): array
    {
        $baseUrl = rtrim((string) config('services.apws.base_url'), '/');
        $cod = trim((string) ($this->repository->getCustomerProviderCod($customerUuid) ?? ''));
        $timeout = (int) config('services.apws.timeout', 60);
        $retries = max(1, (int) config('services.apws.retries', 3));
        $retrySleepMs = max(0, (int) config('services.apws.retry_sleep_ms', 2000));

        if ($baseUrl === '' || $cod === '') {
            throw new ApwsApiException(
                'APWS provider credential is not configured for this customer.',
                403,
                ['customer_uuid' => $customerUuid]
            );
        }

        $query = ['COD' => $cod];
        if (is_string($t1) && $t1 !== '') {
            $query['T1'] = $t1;
        }
        if (is_string($t2) && $t2 !== '') {
            $query['T2'] = $t2;
        }

        $response = Http::timeout($timeout)
            ->retry($retries, $retrySleepMs)
            ->acceptJson()
            ->get($baseUrl, $query);

        if ($response->failed()) {
            throw new ApwsApiException(
                'APWS provider request failed.',
                $response->status(),
                $response->json() ?? $response->body()
            );
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new ApwsApiException('APWS provider returned invalid JSON payload.', $response->status());
        }

        if (!array_key_exists('criativos', $payload) || !array_key_exists('mensagens', $payload)) {
            throw new ApwsApiException('APWS provider payload is missing expected keys.', 502, $payload);
        }

        if (!array_key_exists('veiculacoes', $payload)) {
            $payload['veiculacoes'] = [];
        }

        return [
            'http_status' => $response->status(),
            'payload' => $payload,
        ];
    }
}

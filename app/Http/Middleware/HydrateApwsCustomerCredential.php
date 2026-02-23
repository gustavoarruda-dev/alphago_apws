<?php

namespace App\Http\Middleware;

use App\Repositories\ApwsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HydrateApwsCustomerCredential
{
    public function __construct(private readonly ApwsRepository $repository)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $providerCod = trim((string) $request->header('X-APWS-CREDENTIAL', ''));
        if ($providerCod === '') {
            return $next($request);
        }

        $customerUuid = $this->resolveCustomerUuid($request);
        if ($customerUuid === '') {
            return $next($request);
        }

        try {
            $this->repository->upsertCustomerCredential($customerUuid, $providerCod);
        } catch (\Throwable) {
            // Credential hydration is best-effort and must not block API reads.
        }

        return $next($request);
    }

    private function resolveCustomerUuid(Request $request): string
    {
        $candidates = [
            trim((string) $request->query('customer_uuid', '')),
            trim((string) $request->input('customer_uuid', '')),
            trim((string) $request->header('X-CUSTOMER-UUID', '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Str::isUuid($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}


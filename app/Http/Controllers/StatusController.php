<?php

namespace App\Http\Controllers;

use App\Repositories\ApwsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function __construct(private readonly ApwsRepository $repository)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $customerUuid = trim((string) $request->query('customer_uuid', (string) config('services.apws.default_customer_uuid', '')));
        $hasCustomerScope = $customerUuid !== '';
        $configured = $hasCustomerScope
            ? $this->repository->getCustomerProviderCod($customerUuid) !== null
            : false;
        $cursor = $hasCustomerScope
            ? $this->repository->getCursor('apws', $customerUuid)
            : null;

        return $this->success([
            'configured' => $configured,
            'customer_uuid' => $hasCustomerScope ? $customerUuid : null,
            'provider_base_url' => (string) config('services.apws.base_url'),
            'last_success_t2' => $cursor?->last_success_t2,
            'totals' => [
                'campaigns' => $this->repository->countCampaigns($hasCustomerScope ? $customerUuid : null),
                'placements' => $this->repository->countPlacements($hasCustomerScope ? $customerUuid : null),
            ],
        ], 'APWS integration status');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerUuidRequest;
use App\Http\Requests\SyncRunRequest;
use App\Http\Resources\ApwsSyncRunResource;
use App\Http\Services\ApwsScheduledSyncService;
use App\Repositories\ApwsRepository;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        private readonly ApwsScheduledSyncService $service,
        private readonly ApwsRepository $repository,
    ) {
    }

    public function run(SyncRunRequest $request): JsonResponse
    {
        $run = $this->service->run(
            $request->customerUuid(),
            $request->t1(),
            $request->t2(),
            $request->persistRaw(),
        );

        return $this->success(
            ApwsSyncRunResource::make($run)->resolve(),
            'Sync execution finished',
            $run->status === 'failed' ? 500 : 200
        );
    }

    public function index(CustomerUuidRequest $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $runs = $this->repository->listSyncRuns($request->customerUuid(), $perPage);

        return $this->success([
            'items' => ApwsSyncRunResource::collection($runs->items())->resolve(),
            'pagination' => [
                'current_page' => $runs->currentPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
                'last_page' => $runs->lastPage(),
            ],
        ], 'Sync runs listed');
    }

    public function cursor(CustomerUuidRequest $request): JsonResponse
    {
        $customerUuid = $request->customerUuid();
        $cursor = $this->repository->getCursor('apws', $customerUuid);

        return $this->success([
            'source_key' => 'apws',
            'customer_uuid' => $customerUuid,
            'last_success_t2' => $cursor?->last_success_t2,
        ], 'Current sync cursor');
    }
}

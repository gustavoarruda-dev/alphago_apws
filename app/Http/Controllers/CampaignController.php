<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignIndexRequest;
use App\Http\Requests\CustomerUuidRequest;
use App\Http\Resources\ApwsCreativeResource;
use App\Http\Resources\ApwsPlacementResource;
use App\Http\Services\ApwsQueryService;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    public function __construct(private readonly ApwsQueryService $service)
    {
    }

    public function index(CampaignIndexRequest $request): JsonResponse
    {
        $paginator = $this->service->campaigns(
            $request->filters(),
            $request->page(),
            $request->perPage(),
            $request->sortBy(),
            $request->sortOrder(),
        );

        return $this->success([
            'items' => ApwsCreativeResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Campaigns listed');
    }

    public function show(CustomerUuidRequest $request, string $creativeCode): JsonResponse
    {
        $campaign = $this->service->campaign($request->customerUuid(), $creativeCode);
        if ($campaign === null) {
            return $this->error('Campaign not found.', null, 404);
        }

        return $this->success([
            'campaign' => ApwsCreativeResource::make($campaign)->resolve(),
            'placements' => ApwsPlacementResource::collection($campaign->placements)->resolve(),
        ], 'Campaign detail');
    }
}

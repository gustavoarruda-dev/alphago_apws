<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignIndexRequest;
use App\Http\Services\ApwsQueryService;
use Illuminate\Http\JsonResponse;

class FilterController extends Controller
{
    public function __construct(private readonly ApwsQueryService $service)
    {
    }

    public function index(CampaignIndexRequest $request): JsonResponse
    {
        $filters = $this->service->filters($request->filters());

        return $this->success($filters, 'Filter options listed');
    }
}

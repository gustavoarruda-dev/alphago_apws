<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsRequest;
use App\Http\Services\ApwsQueryService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(private readonly ApwsQueryService $service)
    {
    }

    public function summary(AnalyticsRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->summary($request->filters()),
            'Summary analytics'
        );
    }

    public function brands(AnalyticsRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->brands(
                $request->filters(),
                $request->limit(),
                $request->sortBy('campaigns'),
                $request->sortOrder('desc')
            ),
            'Brand analytics'
        );
    }

    public function products(AnalyticsRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->products(
                $request->filters(),
                $request->limit(),
                $request->sortBy('campaigns'),
                $request->sortOrder('desc')
            ),
            'Product analytics'
        );
    }

    public function regions(AnalyticsRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->regions(
                $request->filters(),
                $request->limit(),
                $request->sortBy('campaigns'),
                $request->sortOrder('desc')
            ),
            'Region analytics'
        );
    }

    public function media(AnalyticsRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->media(
                $request->filters(),
                $request->limit(),
                $request->sortBy('campaigns'),
                $request->sortOrder('desc')
            ),
            'Media analytics'
        );
    }

    public function timeline(AnalyticsRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->timeline(
                $request->filters(),
                $request->groupBy(),
                $request->dimension(),
                $request->sortBy('period'),
                $request->sortOrder('asc')
            ),
            'Timeline analytics'
        );
    }
}

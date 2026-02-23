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
        $rows = $this->service->brands(
            $request->filters(),
            $request->limit(),
            $request->sortBy('campaigns'),
            $request->sortOrder('desc')
        );

        return $this->success(
            $request->shouldPaginate()
                ? $this->paginatePayload($rows, $request->page(), $request->perPage())
                : $rows,
            'Brand analytics'
        );
    }

    public function products(AnalyticsRequest $request): JsonResponse
    {
        $rows = $this->service->products(
            $request->filters(),
            $request->limit(),
            $request->sortBy('campaigns'),
            $request->sortOrder('desc')
        );

        return $this->success(
            $request->shouldPaginate()
                ? $this->paginatePayload($rows, $request->page(), $request->perPage())
                : $rows,
            'Product analytics'
        );
    }

    public function regions(AnalyticsRequest $request): JsonResponse
    {
        $rows = $this->service->regions(
            $request->filters(),
            $request->limit(),
            $request->sortBy('campaigns'),
            $request->sortOrder('desc')
        );

        return $this->success(
            $request->shouldPaginate()
                ? $this->paginatePayload($rows, $request->page(), $request->perPage())
                : $rows,
            'Region analytics'
        );
    }

    public function media(AnalyticsRequest $request): JsonResponse
    {
        $rows = $this->service->media(
            $request->filters(),
            $request->limit(),
            $request->sortBy('campaigns'),
            $request->sortOrder('desc')
        );

        return $this->success(
            $request->shouldPaginate()
                ? $this->paginatePayload($rows, $request->page(), $request->perPage())
                : $rows,
            'Media analytics'
        );
    }

    public function timeline(AnalyticsRequest $request): JsonResponse
    {
        $rows = $this->service->timeline(
            $request->filters(),
            $request->groupBy(),
            $request->dimension(),
            $request->sortBy('period'),
            $request->sortOrder('asc')
        );

        return $this->success(
            $request->shouldPaginate()
                ? $this->paginatePayload($rows, $request->page(), $request->perPage())
                : $rows,
            'Timeline analytics'
        );
    }

    /**
     * @param array<int, mixed> $rows
     * @return array{items: array<int, mixed>, pagination: array{currentPage: int, totalPages: int, pageSize: int, totalItems: int}}
     */
    private function paginatePayload(array $rows, int $page, int $perPage): array
    {
        $safePerPage = max(1, min(100, $perPage));
        $safePage = max(1, $page);
        $totalItems = count($rows);
        $totalPages = max(1, (int) ceil($totalItems / $safePerPage));
        $currentPage = min($safePage, $totalPages);
        $offset = ($currentPage - 1) * $safePerPage;

        return [
            'items' => array_values(array_slice($rows, $offset, $safePerPage)),
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'pageSize' => $safePerPage,
                'totalItems' => $totalItems,
            ],
        ];
    }
}

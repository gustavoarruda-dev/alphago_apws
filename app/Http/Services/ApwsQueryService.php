<?php

namespace App\Http\Services;

use App\Repositories\ApwsRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ApwsQueryService
{
    public function __construct(private readonly ApwsRepository $repository)
    {
    }

    public function campaigns(array $filters, int $page, int $perPage, string $sortBy, string $sortOrder): LengthAwarePaginator
    {
        return $this->repository->paginateCampaigns($filters, $page, $perPage, $sortBy, $sortOrder);
    }

    public function campaign(string $customerUuid, string $creativeCode): ?\App\Models\ApwsCreative
    {
        return $this->repository->findCampaignByCode($customerUuid, $creativeCode);
    }

    public function filters(array $filters = []): array
    {
        return $this->repository->filterOptions($filters);
    }

    public function summary(array $filters): array
    {
        return $this->repository->getSummary($filters);
    }

    public function brands(array $filters, int $limit, string $sortBy = 'campaigns', string $sortOrder = 'desc'): array
    {
        return $this->repository->aggregateBrands($filters, $limit, $sortBy, $sortOrder);
    }

    public function products(array $filters, int $limit, string $sortBy = 'campaigns', string $sortOrder = 'desc'): array
    {
        return $this->repository->aggregateProducts($filters, $limit, $sortBy, $sortOrder);
    }

    public function regions(array $filters, int $limit, string $sortBy = 'campaigns', string $sortOrder = 'desc'): array
    {
        return $this->repository->aggregateRegions($filters, $limit, $sortBy, $sortOrder);
    }

    public function media(array $filters, int $limit, string $sortBy = 'campaigns', string $sortOrder = 'desc'): array
    {
        return $this->repository->aggregateMedia($filters, $limit, $sortBy, $sortOrder);
    }

    public function timeline(
        array $filters,
        string $groupBy,
        string $dimension,
        string $sortBy = 'period',
        string $sortOrder = 'asc'
    ): array
    {
        return $this->repository->timeline($filters, $groupBy, $dimension, $sortBy, $sortOrder);
    }
}

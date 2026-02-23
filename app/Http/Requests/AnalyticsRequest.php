<?php

namespace App\Http\Requests;

class AnalyticsRequest extends CampaignIndexRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'group_by' => ['nullable', 'in:month,day'],
            'dimension' => ['nullable', 'in:brands,products,regions,media'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:label,campaigns,placements,period,total_campaigns,top_label,variation'],
            'sort_order' => ['nullable', 'in:asc,desc'],
        ]);
    }

    public function groupBy(): string
    {
        return (string) ($this->validated()['group_by'] ?? 'month');
    }

    public function dimension(): string
    {
        return (string) ($this->validated()['dimension'] ?? 'brands');
    }

    public function limit(): int
    {
        return (int) ($this->validated()['limit'] ?? 100);
    }

    public function sortBy(string $default = 'campaigns'): string
    {
        return (string) ($this->validated()['sort_by'] ?? $default);
    }

    public function sortOrder(string $default = 'desc'): string
    {
        return (string) ($this->validated()['sort_order'] ?? $default);
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 10);
    }

    public function shouldPaginate(): bool
    {
        return $this->has('page') || $this->has('per_page');
    }
}

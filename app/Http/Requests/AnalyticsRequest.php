<?php

namespace App\Http\Requests;

class AnalyticsRequest extends CampaignIndexRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'group_by' => ['nullable', 'in:month,day'],
            'dimension' => ['nullable', 'in:brands,products,regions,media'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:label,campaigns,placements,period,total_campaigns,top_label'],
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
        return (int) ($this->validated()['limit'] ?? 20);
    }

    public function sortBy(string $default = 'campaigns'): string
    {
        return (string) ($this->validated()['sort_by'] ?? $default);
    }

    public function sortOrder(string $default = 'desc'): string
    {
        return (string) ($this->validated()['sort_order'] ?? $default);
    }
}

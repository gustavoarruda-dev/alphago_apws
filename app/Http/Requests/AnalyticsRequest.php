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
}

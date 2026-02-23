<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CampaignIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['advertisers', 'products', 'media', 'regions', 'vehicles'] as $key) {
            $raw = $this->input($key);
            if (is_string($raw) && $raw !== '') {
                $values = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($v) => $v !== ''));
                $this->merge([$key => $values]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'customer_uuid' => ['required', 'uuid'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'advertisers' => ['nullable', 'array'],
            'advertisers.*' => ['string', 'max:255'],
            'products' => ['nullable', 'array'],
            'products.*' => ['string', 'max:255'],
            'media' => ['nullable', 'array'],
            'media.*' => ['string', 'max:255'],
            'regions' => ['nullable', 'array'],
            'regions.*' => ['string', 'max:32'],
            'vehicles' => ['nullable', 'array'],
            'vehicles.*' => ['string', 'max:255'],
            'campaign_code' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:collect_date,creative_code,campaign_code,media_type,created_at'],
            'sort_order' => ['nullable', 'in:asc,desc'],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'customer_uuid' => $validated['customer_uuid'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'advertisers' => $validated['advertisers'] ?? [],
            'products' => $validated['products'] ?? [],
            'media' => $validated['media'] ?? [],
            'regions' => $validated['regions'] ?? [],
            'vehicles' => $validated['vehicles'] ?? [],
            'campaign_code' => $validated['campaign_code'] ?? null,
            'search' => $validated['search'] ?? null,
        ];
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 20);
    }

    public function sortBy(): string
    {
        return (string) ($this->validated()['sort_by'] ?? 'collect_date');
    }

    public function sortOrder(): string
    {
        return (string) ($this->validated()['sort_order'] ?? 'desc');
    }

    public function customerUuid(): string
    {
        return (string) ($this->validated()['customer_uuid'] ?? '');
    }
}

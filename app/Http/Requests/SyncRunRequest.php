<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid' => ['required', 'uuid'],
            't1' => ['nullable', 'string', 'regex:/^\d{8}T\d{6}$/'],
            't2' => ['nullable', 'string', 'regex:/^\d{8}T\d{6}$/'],
            'persist_raw' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $t1 = $this->input('t1');
            $t2 = $this->input('t2');

            if (!is_string($t1) || !is_string($t2)) {
                return;
            }

            $start = \DateTimeImmutable::createFromFormat('Ymd\\THis', $t1);
            $end = \DateTimeImmutable::createFromFormat('Ymd\\THis', $t2);

            if (!$start || !$end) {
                return;
            }

            if ($start > $end) {
                $validator->errors()->add('t1', 't1 must be less than or equal to t2.');
                return;
            }

            $window = $end->getTimestamp() - $start->getTimestamp();
            if ($window > (30 * 24 * 60 * 60)) {
                $validator->errors()->add('t2', 'The sync window cannot exceed 30 days.');
            }
        });
    }

    public function t1(): ?string
    {
        $value = $this->validated()['t1'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function t2(): ?string
    {
        $value = $this->validated()['t2'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function persistRaw(): bool
    {
        return (bool) ($this->validated()['persist_raw'] ?? true);
    }

    public function customerUuid(): string
    {
        return (string) ($this->validated()['customer_uuid'] ?? '');
    }
}

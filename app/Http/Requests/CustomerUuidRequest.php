<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerUuidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid' => ['required', 'uuid'],
        ];
    }

    public function customerUuid(): string
    {
        return (string) ($this->validated()['customer_uuid'] ?? '');
    }
}


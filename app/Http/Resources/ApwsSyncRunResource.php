<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApwsSyncRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'customer_uuid' => $this->customer_uuid,
            'request_t1' => $this->request_t1,
            'request_t2' => $this->request_t2,
            'response_t1' => $this->response_t1,
            'response_t2' => $this->response_t2,
            'status' => $this->status,
            'http_status' => (int) $this->http_status,
            'provider_message' => $this->provider_message,
            'error_message' => $this->error_message,
            'quantity_creatives' => (int) $this->quantity_creatives,
            'quantity_placements' => (int) $this->quantity_placements,
            'duration_ms' => (int) $this->duration_ms,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}

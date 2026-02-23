<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApwsPlacementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'placement_external_id' => (string) $this->placement_external_id,
            'creative_code' => (string) $this->creative_code,
            'aired_at' => optional($this->aired_at)?->toIso8601String(),
            'place_raw' => $this->place_raw,
            'city' => $this->city,
            'state' => $this->state,
            'vehicle' => $this->vehicle,
        ];
    }
}

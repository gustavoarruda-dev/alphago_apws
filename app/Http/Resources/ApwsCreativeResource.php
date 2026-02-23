<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApwsCreativeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'creative_code' => (string) $this->creative_code,
            'campaign_code' => (string) $this->campaign_code,
            'media_type' => (string) $this->media_type,
            'collect_date' => optional($this->collect_date)?->toDateString(),
            'collect_city' => $this->collect_city,
            'collect_state' => $this->collect_state,
            'collect_vehicle' => $this->collect_vehicle,
            'primary_file_type' => $this->primary_file_type,
            'has_stored_media' => $this->stored_media_path !== null && trim((string) $this->stored_media_path) !== '',
            'stored_media_mime' => $this->stored_media_mime,
            'stored_media_size' => $this->stored_media_size !== null ? (int) $this->stored_media_size : null,
            'advertisers' => $this->whenLoaded('advertisers', function () {
                return $this->advertisers->pluck('advertiser')->values()->all();
            }, []),
            'products' => $this->whenLoaded('products', function () {
                return $this->products->pluck('product')->values()->all();
            }, []),
            'placements_count' => $this->when(isset($this->placements_count), (int) $this->placements_count),
            'raw_payload' => $this->when((bool) $request->boolean('include_raw', false), $this->raw_payload),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'service_id' => $this->service_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category_badge' => $this->category_badge,
            'sub_category_badge' => $this->sub_category_badge,
            'description' => $this->description,
            'sla_duration' => $this->sla_duration,
            'cost_info' => $this->cost_info,
            'officer_in_charge' => $this->officer_in_charge,
            'download_url' => $this->download_url,
            'tags' => $this->tags ?? [],
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

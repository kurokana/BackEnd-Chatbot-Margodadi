<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UmkmDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'umkm_id' => $this->umkm_id,
            'reg_number' => $this->reg_number,
            'name' => $this->name,
            'sub_title' => $this->sub_title,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'wa_number' => $this->wa_number,
            'address' => $this->address,
            'description' => $this->description,
            'history' => $this->history,
            'banner_image_url' => $this->banner_image_url,
            'gallery_urls' => $this->gallery_urls ?? [],
            'legal_certification' => $this->legal_certification,
            'legal_number' => $this->legal_number,
            'production_capacity' => $this->production_capacity,
            'capacity_note' => $this->capacity_note,
            'group_name' => $this->group_name,
            'group_location' => $this->group_location,
            'map_title' => $this->map_title,
            'map_address' => $this->map_address,
            'map_url' => $this->map_url,
            'is_active' => $this->is_active,
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'products' => UmkmProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

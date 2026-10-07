<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UmkmResource extends JsonResource
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
            'banner_image_url' => $this->banner_image_url,
            'legal_certification' => $this->legal_certification,
            'group_name' => $this->group_name,
            'group_location' => $this->group_location,
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'products_count' => $this->whenCounted('products'),
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

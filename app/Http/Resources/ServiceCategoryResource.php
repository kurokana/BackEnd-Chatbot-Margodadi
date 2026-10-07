<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category_id' => $this->category_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain?->value ?? $this->domain,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'public_services_count' => $this->whenCounted('publicServices'),
            'umkms_count' => $this->whenCounted('umkms'),
        ];
    }
}

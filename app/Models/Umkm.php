<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Umkm extends Model
{
    use HasFactory;

    protected $table = 'umkms';
    protected $primaryKey = 'umkm_id';

    protected $fillable = [
        'category_id',
        'reg_number',
        'name',
        'sub_title',
        'owner_name',
        'phone',
        'wa_number',
        'address',
        'description',
        'history',
        'banner_image_url',
        'gallery_urls',
        'legal_certification',
        'legal_number',
        'production_capacity',
        'capacity_note',
        'group_name',
        'group_location',
        'map_title',
        'map_address',
        'map_url',
        'last_verified_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery_urls' => 'array',
            'last_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id', 'category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(UmkmProduct::class, 'umkm_id', 'umkm_id');
    }

    /**
     * Scope query to only active UMKMs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to search across various UMKM fields.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $searchTerm = '%' . trim($term) . '%';

        return $query->where(function (Builder $q) use ($searchTerm) {
            $q->where('name', 'like', $searchTerm)
                ->orWhere('sub_title', 'like', $searchTerm)
                ->orWhere('owner_name', 'like', $searchTerm)
                ->orWhere('description', 'like', $searchTerm)
                ->orWhere('history', 'like', $searchTerm)
                ->orWhere('legal_certification', 'like', $searchTerm)
                ->orWhere('legal_number', 'like', $searchTerm)
                ->orWhere('group_name', 'like', $searchTerm)
                ->orWhere('reg_number', 'like', $searchTerm)
                ->orWhere('address', 'like', $searchTerm);
        });
    }

    /**
     * Scope query to filter by category ID or category slug.
     */
    public function scopeFilterCategory(Builder $query, ?string $category): Builder
    {
        if (blank($category)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($category) {
            if (is_numeric($category)) {
                $q->where('category_id', (int) $category);
            } else {
                $q->whereHas('category', function (Builder $cq) use ($category) {
                    $cq->where('slug', $category);
                });
            }
        });
    }

    /**
     * Scope query to sort UMKM results.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match (strtolower((string) $sort)) {
            'a-z', 'name_asc' => $query->orderBy('name', 'asc'),
            'z-a', 'name_desc' => $query->orderBy('name', 'desc'),
            'oldest', 'terlama' => $query->orderBy('umkm_id', 'asc'),
            'newest', 'latest', 'terbaru' => $query->orderBy('umkm_id', 'desc'),
            default => $query->orderBy('umkm_id', 'desc'),
        };
    }
}

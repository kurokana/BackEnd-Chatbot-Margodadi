<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicService extends Model
{
    use HasFactory;

    protected $table = 'public_services';
    protected $primaryKey = 'service_id';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'category_badge',
        'sub_category_badge',
        'description',
        'legal_basis',
        'requirements',
        'steps',
        'sla_duration',
        'cost_info',
        'officer_in_charge',
        'download_url',
        'tags',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'steps' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id', 'category_id');
    }

    /**
     * Scope query to active public services.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to search by keywords.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $searchTerm = '%' . trim($term) . '%';

        return $query->where(function (Builder $q) use ($searchTerm) {
            $q->where('title', 'like', $searchTerm)
                ->orWhere('description', 'like', $searchTerm)
                ->orWhere('legal_basis', 'like', $searchTerm)
                ->orWhere('officer_in_charge', 'like', $searchTerm)
                ->orWhere('category_badge', 'like', $searchTerm)
                ->orWhere('sub_category_badge', 'like', $searchTerm)
                ->orWhere('tags', 'like', $searchTerm);
        });
    }

    /**
     * Scope query to filter by category ID or slug.
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
     * Scope query to filter by category badge.
     */
    public function scopeFilterBadge(Builder $query, ?string $badge): Builder
    {
        if (blank($badge)) {
            return $query;
        }

        return $query->where('category_badge', $badge);
    }

    /**
     * Scope query to filter by sub category badge.
     */
    public function scopeFilterSubBadge(Builder $query, ?string $subBadge): Builder
    {
        if (blank($subBadge)) {
            return $query;
        }

        return $query->where('sub_category_badge', $subBadge);
    }

    /**
     * Scope query to sort results.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('service_id', 'asc'),
            'title_asc' => $query->orderBy('title', 'asc'),
            'title_desc' => $query->orderBy('title', 'desc'),
            default => $query->orderBy('service_id', 'desc'),
        };
    }
}

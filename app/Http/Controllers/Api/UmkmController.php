<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceDomain;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Resources\UmkmDetailResource;
use App\Http\Resources\UmkmResource;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UmkmController extends Controller
{
    /**
     * Get list of UMKMs with search, category filter, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        $searchTerm = $request->input('search', $request->input('q'));
        $category = $request->input('category');
        $sort = $request->input('sort', 'latest');

        $umkms = Umkm::query()
            ->active()
            ->with(['category'])
            ->withCount(['products' => fn ($q) => $q->active()])
            ->search($searchTerm)
            ->filterCategory($category)
            ->sort($sort)
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => UmkmResource::collection($umkms->items()),
            'pagination' => [
                'current_page' => $umkms->currentPage(),
                'per_page' => $umkms->perPage(),
                'total' => $umkms->total(),
                'last_page' => $umkms->lastPage(),
                'has_more_pages' => $umkms->hasMorePages(),
            ],
            'filters_applied' => array_filter([
                'search' => $searchTerm,
                'category' => $category,
                'sort' => $sort,
            ]),
        ]);
    }

    /**
     * Get detail of a single UMKM with its products and category.
     */
    public function show(string $id): JsonResponse
    {
        $umkm = Umkm::query()
            ->active()
            ->with([
                'category',
                'products' => fn ($q) => $q->active(),
            ])
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('umkm_id', (int) $id);
                } else {
                    $q->where('reg_number', $id);
                }
            })
            ->first();

        if (! $umkm) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data UMKM tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new UmkmDetailResource($umkm),
        ]);
    }

    /**
     * Get list of UMKM categories with active UMKM counts.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->active()
            ->domain(ServiceDomain::UMKM)
            ->withCount(['umkms' => fn ($q) => $q->active()])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ServiceCategoryResource::collection($categories),
        ]);
    }
}

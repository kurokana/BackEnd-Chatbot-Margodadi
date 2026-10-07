<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceDomain;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicServiceDetailResource;
use App\Http\Resources\PublicServiceResource;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\PublicService;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicServiceController extends Controller
{
    /**
     * Get list of public services with search, filter, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        $searchTerm = $request->input('search', $request->input('q'));
        $category = $request->input('category');
        $badge = $request->input('badge');
        $subBadge = $request->input('sub_badge');
        $sort = $request->input('sort', 'latest');

        $services = PublicService::query()
            ->active()
            ->with('category')
            ->search($searchTerm)
            ->filterCategory($category)
            ->filterBadge($badge)
            ->filterSubBadge($subBadge)
            ->sort($sort)
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => PublicServiceResource::collection($services->items()),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
                'has_more_pages' => $services->hasMorePages(),
            ],
            'filters_applied' => array_filter([
                'search' => $searchTerm,
                'category' => $category,
                'badge' => $badge,
                'sub_badge' => $subBadge,
                'sort' => $sort,
            ]),
        ]);
    }

    /**
     * Get detail of a single public service by slug or ID.
     */
    public function show(string $slug): JsonResponse
    {
        $service = PublicService::query()
            ->active()
            ->with('category')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('service_id', (int) $slug);
                }
            })
            ->first();

        if (! $service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Layanan publik tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new PublicServiceDetailResource($service),
        ]);
    }

    /**
     * Get list of public service categories with services count.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->active()
            ->domain(ServiceDomain::PUBLIC_SERVICE)
            ->withCount(['publicServices' => fn ($q) => $q->active()])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ServiceCategoryResource::collection($categories),
        ]);
    }
}

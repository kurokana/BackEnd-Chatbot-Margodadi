<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicServiceResource;
use App\Http\Resources\UmkmResource;
use App\Models\PublicService;
use App\Models\Umkm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Search across public services and UMKMs for homepage search bar.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', $request->input('search', $request->input('query', ''))));
        $type = strtolower((string) $request->input('type', 'all'));
        $limit = (int) $request->input('limit', 10);
        $limit = max(1, min($limit, 50));

        if (blank($term)) {
            return response()->json([
                'status' => 'success',
                'query' => '',
                'total_results' => 0,
                'data' => [
                    'public_services' => [],
                    'umkms' => [],
                ],
                'counts' => [
                    'public_services' => 0,
                    'umkms' => 0,
                ],
            ]);
        }

        $publicServices = [];
        $umkms = [];

        // Search in Public Services
        if ($type === 'all' || $type === 'public_services' || $type === 'layanan') {
            $publicServices = PublicService::query()
                ->active()
                ->with('category')
                ->search($term)
                ->limit($limit)
                ->get();
        }

        // Search in UMKMs
        if ($type === 'all' || $type === 'umkms' || $type === 'umkm') {
            $umkms = Umkm::query()
                ->active()
                ->with('category')
                ->withCount(['products' => fn ($q) => $q->active()])
                ->search($term)
                ->limit($limit)
                ->get();
        }

        $publicServicesCount = is_countable($publicServices) ? count($publicServices) : 0;
        $umkmsCount = is_countable($umkms) ? count($umkms) : 0;

        return response()->json([
            'status' => 'success',
            'query' => $term,
            'total_results' => $publicServicesCount + $umkmsCount,
            'data' => [
                'public_services' => PublicServiceResource::collection($publicServices),
                'umkms' => UmkmResource::collection($umkms),
            ],
            'counts' => [
                'public_services' => $publicServicesCount,
                'umkms' => $umkmsCount,
            ],
        ]);
    }
}

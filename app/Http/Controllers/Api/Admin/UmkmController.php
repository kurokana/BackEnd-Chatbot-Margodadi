<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChannelType;
use App\Enums\ServiceDomain;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use App\Models\UmkmProduct;
use App\Services\UmkmAiKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UmkmController extends Controller
{
    public function __construct(
        protected UmkmAiKnowledgeService $aiKnowledgeService
    ) {}

    /**
     * Get list of UMKMs with search, category, status, and verification filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Umkm::with([
            'category',
            'products',
            'kbDocument.chunks',
        ]);

        // Search term
        if ($search = $request->input('search')) {
            $term = '%' . trim(strtolower($search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(owner_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(reg_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$term])
                    ->orWhereHas('products', function ($pq) use ($term) {
                        $pq->whereRaw('LOWER(name) LIKE ?', [$term]);
                    });
            });
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            if ($categoryId !== 'all') {
                $query->where('category_id', (int) $categoryId);
            }
        }

        // Status filter (all, active, inactive)
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Verification filter (all, verified, unverified)
        $verified = $request->input('verified', 'all');
        if ($verified === 'verified') {
            $query->whereNotNull('last_verified_at');
        } elseif ($verified === 'unverified') {
            $query->whereNull('last_verified_at');
        }

        // AI Sync filter
        $aiSync = $request->input('ai_synced', 'all');
        if ($aiSync === 'synced') {
            $query->whereNotNull('rag_document_id');
        } elseif ($aiSync === 'unsynced') {
            $query->whereNull('rag_document_id');
        }

        // Sorting
        $sort = strtolower((string) $request->input('sort', 'latest'));
        match ($sort) {
            'a-z', 'az' => $query->orderBy('name', 'asc'),
            'z-a', 'za' => $query->orderBy('name', 'desc'),
            'oldest' => $query->orderBy('umkm_id', 'asc'),
            default => $query->orderBy('umkm_id', 'desc'),
        };

        $paginated = $query->paginate($perPage);

        $formatted = collect($paginated->items())->map(fn (Umkm $u) => $this->formatUmkmRow($u));

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get KPI summary statistics for UMKM Back-Office.
     */
    public function stats(): JsonResponse
    {
        $totalUmkm = Umkm::count();
        $activeUmkm = Umkm::where('is_active', true)->count();
        $inactiveUmkm = Umkm::where('is_active', false)->count();
        $verifiedUmkm = Umkm::whereNotNull('last_verified_at')->count();
        $totalProducts = UmkmProduct::count();
        $aiIndexedCount = Umkm::whereNotNull('rag_document_id')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_umkm' => $totalUmkm,
                'active_umkm' => $activeUmkm,
                'inactive_umkm' => $inactiveUmkm,
                'verified_umkm' => $verifiedUmkm,
                'unverified_umkm' => $totalUmkm - $verifiedUmkm,
                'total_products' => $totalProducts,
                'ai_indexed_count' => $aiIndexedCount,
                'ai_indexed_percent' => $totalUmkm > 0 ? round(($aiIndexedCount / $totalUmkm) * 100) : 0,
            ],
        ]);
    }

    /**
     * Get category list for UMKM domain.
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->where('domain', ServiceDomain::UMKM)
            ->where('is_active', true)
            ->withCount(['umkms'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($cat) => [
                'category_id' => $cat->category_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'icon' => $cat->icon,
                'umkms_count' => $cat->umkms_count,
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Get detail of a specific UMKM.
     */
    public function show(int $id): JsonResponse
    {
        $umkm = Umkm::with(['category', 'products', 'kbDocument.chunks'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatUmkmDetail($umkm),
        ]);
    }

    /**
     * Create a new UMKM record with physical image upload and auto AI synchronization.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUmkmRequest($request);

        // Handle auto-generated reg number if not provided
        $regNumber = $validated['reg_number'] ?? null;
        if (blank($regNumber)) {
            $lastId = (int) Umkm::max('umkm_id') + 1;
            $regNumber = 'MKD-UMKM-' . str_pad($lastId, 3, '0', STR_PAD_LEFT);
        }

        // Handle Banner Image Upload (Physical file only: png, jpg, jpeg)
        $bannerUrl = null;
        if ($request->hasFile('banner_image')) {
            $bannerPath = $request->file('banner_image')->store('umkms/banners', 'public');
            $bannerUrl = asset('storage/' . $bannerPath);
        }

        // Handle Gallery Images Upload (Physical files: png, jpg, jpeg)
        $galleryUrls = [];
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $galFile) {
                $galPath = $galFile->store('umkms/gallery', 'public');
                $galleryUrls[] = asset('storage/' . $galPath);
            }
        }

        $umkm = DB::transaction(function () use ($validated, $regNumber, $bannerUrl, $galleryUrls, $request) {
            $newUmkm = Umkm::create([
                'category_id' => $validated['category_id'] ?? null,
                'reg_number' => $regNumber,
                'name' => $validated['name'],
                'sub_title' => $validated['sub_title'] ?? null,
                'owner_name' => $validated['owner_name'],
                'phone' => $validated['phone'] ?? null,
                'wa_number' => $validated['wa_number'] ?? (!empty($validated['phone']) ? preg_replace('/[^0-9]/', '', (string) $validated['phone']) : null),
                'address' => $validated['address'] ?? null,
                'description' => $validated['description'] ?? null,
                'history' => $validated['history'] ?? null,
                'banner_image_url' => $bannerUrl,
                'gallery_urls' => $galleryUrls,
                'legal_certification' => $validated['legal_certification'] ?? null,
                'legal_number' => $validated['legal_number'] ?? null,
                'production_capacity' => $validated['production_capacity'] ?? null,
                'capacity_note' => $validated['capacity_note'] ?? null,
                'group_name' => $validated['group_name'] ?? null,
                'group_location' => $validated['group_location'] ?? null,
                'map_title' => $validated['map_title'] ?? null,
                'map_address' => $validated['map_address'] ?? null,
                'map_url' => $validated['map_url'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'last_verified_at' => $request->boolean('is_verified', false) ? now() : null,
            ]);

            // Save Products if provided
            $productsInput = $this->parseJsonInput($request->input('products'));
            if (!empty($productsInput) && is_array($productsInput)) {
                foreach ($productsInput as $prod) {
                    if (!empty($prod['name'])) {
                        UmkmProduct::create([
                            'umkm_id' => $newUmkm->umkm_id,
                            'name' => $prod['name'],
                            'price' => $prod['price'] ?? null,
                            'description' => $prod['description'] ?? null,
                            'is_active' => $prod['is_active'] ?? true,
                        ]);
                    }
                }
            }

            return $newUmkm;
        });

        // Auto-Sync to Python RAG & Knowledge Base
        $aiSyncResult = $this->aiKnowledgeService->syncToRag($umkm);

        // Activity Audit Log
        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'UMKM_CREATE',
            'target' => $umkm->reg_number,
            'description' => "Menambahkan data UMKM baru: '{$umkm->name}' beserta " . $umkm->products()->count() . " produk unggulan (AI sync: " . ($aiSyncResult['status'] ?? 'pending') . ")",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data UMKM berhasil disimpan dan disinkronkan ke sistem.',
            'ai_sync' => $aiSyncResult,
            'data' => $this->formatUmkmDetail($umkm->fresh(['category', 'products', 'kbDocument'])),
        ], 201);
    }

    /**
     * Update an existing UMKM record and re-sync AI vector representation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $umkm = Umkm::with('products')->findOrFail($id);
        $validated = $this->validateUmkmRequest($request, $id);

        // Handle Banner Image Replacement or Removal
        $bannerUrl = $umkm->banner_image_url;
        if ($request->boolean('remove_banner')) {
            $bannerUrl = null;
        } elseif ($request->hasFile('banner_image')) {
            $bannerPath = $request->file('banner_image')->store('umkms/banners', 'public');
            $bannerUrl = asset('storage/' . $bannerPath);
        }

        // Handle Retained Gallery Images (Start with retained list if sent, else existing)
        $existingGallery = $umkm->gallery_urls ?? [];
        if ($request->has('retained_gallery_urls')) {
            $retained = $this->parseJsonInput($request->input('retained_gallery_urls'));
            if (is_array($retained)) {
                $existingGallery = $retained;
            }
        }

        // Append newly uploaded gallery images
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $galFile) {
                $galPath = $galFile->store('umkms/gallery', 'public');
                $existingGallery[] = asset('storage/' . $galPath);
            }
        }

        // Build old markdown profile before updating database to detect text changes
        $oldMarkdown = $this->aiKnowledgeService->buildMarkdownProfile($umkm);

        DB::transaction(function () use ($umkm, $validated, $bannerUrl, $existingGallery, $request) {
            $umkm->update([
                'category_id' => $validated['category_id'] ?? $umkm->category_id,
                'name' => $validated['name'],
                'sub_title' => $validated['sub_title'] ?? null,
                'owner_name' => $validated['owner_name'],
                'phone' => $validated['phone'] ?? null,
                'wa_number' => $validated['wa_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'description' => $validated['description'] ?? null,
                'history' => $validated['history'] ?? null,
                'banner_image_url' => $bannerUrl,
                'gallery_urls' => $existingGallery,
                'legal_certification' => $validated['legal_certification'] ?? null,
                'legal_number' => $validated['legal_number'] ?? null,
                'production_capacity' => $validated['production_capacity'] ?? null,
                'capacity_note' => $validated['capacity_note'] ?? null,
                'group_name' => $validated['group_name'] ?? null,
                'group_location' => $validated['group_location'] ?? null,
                'map_title' => $validated['map_title'] ?? null,
                'map_address' => $validated['map_address'] ?? null,
                'map_url' => $validated['map_url'] ?? null,
                'is_active' => $request->boolean('is_active', $umkm->is_active),
            ]);

            // Sync Products
            if ($request->has('products')) {
                $productsInput = $this->parseJsonInput($request->input('products'));
                if (is_array($productsInput)) {
                    $umkm->products()->delete();
                    foreach ($productsInput as $prod) {
                        if (!empty($prod['name'])) {
                            UmkmProduct::create([
                                'umkm_id' => $umkm->umkm_id,
                                'name' => $prod['name'],
                                'price' => $prod['price'] ?? null,
                                'description' => $prod['description'] ?? null,
                                'is_active' => $prod['is_active'] ?? true,
                            ]);
                        }
                    }
                }
            }
        });

        // Smart Check: Only trigger Python RAG embedding if text profile actually changed
        $freshUmkm = $umkm->fresh(['category', 'products']);
        $newMarkdown = $this->aiKnowledgeService->buildMarkdownProfile($freshUmkm);
        $isContentChanged = ($oldMarkdown !== $newMarkdown);

        if ($isContentChanged) {
            $aiSyncResult = $this->aiKnowledgeService->syncToRag($freshUmkm);
            $syncDesc = "AI re-index: " . ($aiSyncResult['status'] ?? 'pending');
        } else {
            $aiSyncResult = [
                'status' => 'skipped',
                'message' => 'Konten teks UMKM tidak berubah, sinkronisasi AI dilewati.',
            ];
            $syncDesc = "gambar/media diperbarui, AI sync dilewati";
        }

        // Activity Audit Log
        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'UMKM_UPDATE',
            'target' => $umkm->reg_number,
            'description' => "Memperbarui data UMKM: '{$umkm->name}' ({$syncDesc})",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $isContentChanged
                ? 'Data UMKM berhasil diperbarui dan disinkronkan ke AI Virtual Guide.'
                : 'Data UMKM dan gambar berhasil diperbarui.',
            'ai_sync' => $aiSyncResult,
            'data' => $this->formatUmkmDetail($freshUmkm->loadMissing(['kbDocument'])),
        ]);
    }

    /**
     * Delete an UMKM record, products, and linked AI vector document.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $umkm = Umkm::findOrFail($id);
        $name = $umkm->name;
        $regNumber = $umkm->reg_number;

        // Delete from Python RAG & KB
        $this->aiKnowledgeService->deleteFromRag($umkm);

        // Delete UMKM & Products
        $umkm->products()->delete();
        $umkm->delete();

        // Activity Log
        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'UMKM_DELETE',
            'target' => $regNumber,
            'description' => "Menghapus profil UMKM: '{$name}' dan seluruh data vektor AI terkait",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Data UMKM '{$name}' berhasil dihapus dari sistem dan AI.",
        ]);
    }

    /**
     * Quick toggle active status of UMKM.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $umkm = Umkm::with(['category', 'products'])->findOrFail($id);
        $umkm->is_active = $request->boolean('is_active');
        $umkm->save();

        // Update AI RAG sync
        $this->aiKnowledgeService->syncToRag($umkm);

        $statusLabel = $umkm->is_active ? 'diaktifkan' : 'dinonaktifkan';

        // Activity Log
        ActivityLog::create([
            'operator_id' => $request->user()?->operator_id,
            'actor_name' => $request->user()?->name ?? 'Aparatur Pekon',
            'action' => 'UMKM_STATUS_TOGGLE',
            'target' => $umkm->reg_number,
            'description' => "Status UMKM '{$umkm->name}' diubah menjadi {$statusLabel}",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Status UMKM berhasil {$statusLabel}.",
            'data' => [
                'umkm_id' => $umkm->umkm_id,
                'is_active' => $umkm->is_active,
            ],
        ]);
    }

    /**
     * Mark an UMKM as verified by Pekon apparatus.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $umkm = Umkm::findOrFail($id);
        $umkm->last_verified_at = now();
        $umkm->save();

        ActivityLog::create([
            'operator_id' => $request->user()?->operator_id,
            'actor_name' => $request->user()?->name ?? 'Aparatur Pekon',
            'action' => 'UMKM_VERIFY',
            'target' => $umkm->reg_number,
            'description' => "Aparatur memverifikasi kelayakan legalitas UMKM '{$umkm->name}'",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "UMKM '{$umkm->name}' berhasil diverifikasi.",
            'data' => [
                'umkm_id' => $umkm->umkm_id,
                'last_verified_at' => $umkm->last_verified_at->toIso8601String(),
                'last_verified_formatted' => $umkm->last_verified_at->translatedFormat('d F Y'),
            ],
        ]);
    }

    /**
     * Trigger explicit single UMKM re-indexing to Python AI RAG.
     */
    public function syncToRag(int $id): JsonResponse
    {
        $umkm = Umkm::with(['category', 'products'])->findOrFail($id);
        $result = $this->aiKnowledgeService->syncToRag($umkm);

        return response()->json([
            'status' => 'success',
            'message' => "Data UMKM '{$umkm->name}' berhasil disinkronkan ke AI Virtual Guide.",
            'data' => $result,
        ]);
    }

    /**
     * Bulk trigger synchronization of all active UMKMs to Python AI RAG.
     */
    public function syncAllToRag(Request $request): JsonResponse
    {
        $result = $this->aiKnowledgeService->syncAll();

        ActivityLog::create([
            'operator_id' => $request->user()?->operator_id,
            'actor_name' => $request->user()?->name ?? 'Aparatur Pekon',
            'action' => 'UMKM_AI_BULK_SYNC',
            'target' => 'ALL_UMKMS',
            'description' => "Sinkronisasi massal seluruh UMKM ({$result['success']}/{$result['total']}) ke AI Vector Store",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menyinkronkan {$result['success']} dari {$result['total']} UMKM ke basis pengetahuan AI Virtual Guide.",
            'data' => $result,
        ]);
    }

    /**
     * Helper to parse JSON input or array string from FormData.
     */
    private function parseJsonInput(mixed $input): mixed
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $input;
        }
        return $input;
    }

    /**
     * Validation rules for UMKM request.
     */
    private function validateUmkmRequest(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:service_categories,category_id'],
            'name' => ['required', 'string', 'max:150'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'wa_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'legal_certification' => ['nullable', 'string', 'max:150'],
            'legal_number' => ['nullable', 'string', 'max:150'],
            'production_capacity' => ['nullable', 'string', 'max:100'],
            'capacity_note' => ['nullable', 'string', 'max:150'],
            'group_name' => ['nullable', 'string', 'max:150'],
            'group_location' => ['nullable', 'string', 'max:150'],
            'map_title' => ['nullable', 'string', 'max:150'],
            'map_address' => ['nullable', 'string'],
            'map_url' => ['nullable', 'string'],
            'banner_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'gallery_images.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
        ], [
            'banner_image.uploaded' => 'Foto banner profil gagal diunggah (ukuran file melebihi batas server).',
            'gallery_images.*.uploaded' => 'Salah satu foto galeri gagal diunggah (ukuran file melebihi batas server).',
            'banner_image.mimes' => 'Format foto banner harus berformat PNG, JPG, atau JPEG.',
            'gallery_images.*.mimes' => 'Format foto galeri harus berformat PNG, JPG, atau JPEG.',
        ]);
    }

    /**
     * Format UMKM for table row display.
     */
    private function formatUmkmRow(Umkm $u): array
    {
        return [
            'id' => $u->umkm_id,
            'reg_number' => $u->reg_number,
            'name' => $u->name,
            'sub_title' => $u->sub_title,
            'owner_name' => $u->owner_name,
            'phone' => $u->phone,
            'wa_number' => $u->wa_number,
            'address' => $u->address,
            'banner_image_url' => $u->banner_image_url,
            'category' => $u->category ? [
                'id' => $u->category->category_id,
                'name' => $u->category->name,
                'slug' => $u->category->slug,
            ] : null,
            'products_count' => $u->products->count(),
            'products_preview' => $u->products->take(3)->map(fn ($p) => [
                'name' => $p->name,
                'price' => $p->price,
            ]),
            'is_active' => (bool) $u->is_active,
            'is_verified' => $u->last_verified_at !== null,
            'last_verified_at' => $u->last_verified_at?->toIso8601String(),
            'last_verified_formatted' => $u->last_verified_at ? $u->last_verified_at->translatedFormat('d M Y') : 'Belum Terverifikasi',
            'is_ai_synced' => $u->rag_document_id !== null,
            'rag_document_id' => $u->rag_document_id,
            'ai_synced_at' => $u->ai_synced_at?->toIso8601String(),
            'ai_synced_formatted' => $u->ai_synced_at ? $u->ai_synced_at->translatedFormat('d M Y, H:i') : null,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format detailed UMKM object for editing and preview modal.
     */
    private function formatUmkmDetail(Umkm $u): array
    {
        return [
            'id' => $u->umkm_id,
            'category_id' => $u->category_id,
            'reg_number' => $u->reg_number,
            'name' => $u->name,
            'sub_title' => $u->sub_title,
            'owner_name' => $u->owner_name,
            'phone' => $u->phone,
            'wa_number' => $u->wa_number,
            'address' => $u->address,
            'description' => $u->description,
            'history' => $u->history,
            'banner_image_url' => $u->banner_image_url,
            'gallery_urls' => $u->gallery_urls ?: [],
            'legal_certification' => $u->legal_certification,
            'legal_number' => $u->legal_number,
            'production_capacity' => $u->production_capacity,
            'capacity_note' => $u->capacity_note,
            'group_name' => $u->group_name,
            'group_location' => $u->group_location,
            'map_title' => $u->map_title,
            'map_address' => $u->map_address,
            'map_url' => $u->map_url,
            'is_active' => (bool) $u->is_active,
            'last_verified_at' => $u->last_verified_at?->toIso8601String(),
            'category' => $u->category ? [
                'id' => $u->category->category_id,
                'name' => $u->category->name,
                'slug' => $u->category->slug,
            ] : null,
            'products' => $u->products->map(fn ($p) => [
                'product_id' => $p->product_id,
                'name' => $p->name,
                'price' => $p->price,
                'description' => $p->description,
                'is_active' => (bool) $p->is_active,
            ]),
            'is_ai_synced' => $u->rag_document_id !== null,
            'rag_document_id' => $u->rag_document_id,
            'ai_synced_at' => $u->ai_synced_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
            'updated_at' => $u->updated_at?->toIso8601String(),
        ];
    }
}

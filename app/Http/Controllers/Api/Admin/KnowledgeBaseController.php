<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChannelType;
use App\Enums\ServiceDomain;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KbChunk;
use App\Models\KbDocument;
use App\Models\RagConfiguration;
use App\Services\RagGatewayService;
use App\Services\VectorChunkingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        protected VectorChunkingService $chunkingService,
        protected RagGatewayService $ragGateway
    ) {}

    /**
     * Get summary metrics for Knowledge Base and Vector Store.
     */
    public function stats(): JsonResponse
    {
        $totalDocs = KbDocument::count();
        $activeDocs = KbDocument::where('is_active', true)->count();
        $totalChunks = KbChunk::count();
        try {
            $ragDocuments = $this->ragGateway->documents();
            if (!empty($ragDocuments)) {
                $totalChunks = collect($ragDocuments)->sum(fn ($doc) => (int) ($doc['chunk_count'] ?? 0));
            }
        } catch (Throwable $e) {
            Log::debug('Python RAG stats unavailable', ['error' => $e->getMessage()]);
        }
        
        $domainBreakdown = [
            'PUBLIC_SERVICE' => KbDocument::where('domain', ServiceDomain::PUBLIC_SERVICE)->count(),
            'UMKM' => KbDocument::where('domain', ServiceDomain::UMKM)->count(),
            'WASTE_EDUCATION' => KbDocument::where('domain', ServiceDomain::WASTE_EDUCATION)->count(),
        ];

        $ragConfig = RagConfiguration::where('is_active', true)->latest('created_at')->first();

        $avgChunksPerDoc = $totalDocs > 0 ? round($totalChunks / $totalDocs, 1) : 0;
        $lastIndexed = KbChunk::latest('created_at')->value('created_at');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_documents' => $totalDocs,
                'active_documents' => $activeDocs,
                'total_chunks' => $totalChunks,
                'avg_chunks_per_doc' => $avgChunksPerDoc,
                'domain_breakdown' => $domainBreakdown,
                'embedding_model' => $ragConfig->embedding_model ?? 'text-embedding-3-small',
                'llm_model' => $ragConfig->llm_model ?? 'gpt-4o-mini',
                'chunk_size' => $ragConfig->chunking_config['size'] ?? 500,
                'chunk_overlap' => $ragConfig->chunking_config['overlap'] ?? 50,
                'last_indexed_at' => $lastIndexed ? Carbon::parse($lastIndexed)->translatedFormat('d M Y, H:i') . ' WIB' : 'Belum pernah',
            ],
        ]);
    }

    /**
     * Display a listing of knowledge documents.
     */
    public function index(Request $request): JsonResponse
    {
        $query = KbDocument::query()
            ->select([
                'document_id',
                'title',
                'domain',
                'source',
                'validator',
                'version',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with(['chunks' => function ($q) {
                $q->select('chunk_id', 'document_id', 'metadata')->orderBy('chunk_index', 'asc');
            }])
            ->withCount('chunks')
            ->orderBy('updated_at', 'desc');

        if ($domain = $request->query('domain')) {
            if (strtoupper($domain) !== 'ALL') {
                $query->where('domain', strtoupper($domain));
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = $request->query('search')) {
            $searchTerm = '%' . strtolower(trim($search)) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(source) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(validator) LIKE ?', [$searchTerm])
                  ->orWhereHas('chunks', function ($cq) use ($searchTerm) {
                      $cq->whereRaw('LOWER(content) LIKE ?', [$searchTerm]);
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $documents = $query->paginate($perPage);

        $formatted = collect($documents->items())->map(function (KbDocument $doc) {
            $firstChunkMetadata = $doc->chunks->first()?->metadata ?? [];

            return [
                'id' => $doc->document_id,
                'document_id' => $doc->document_id,
                'doc_code' => 'DOC-' . str_pad($doc->document_id, 3, '0', STR_PAD_LEFT),
                'title' => $doc->title,
                'domain' => $doc->domain instanceof ServiceDomain ? $doc->domain->value : (string) $doc->domain,
                'source' => $doc->source ?? '-',
                'validator' => $doc->validator ?? 'Aparatur Pekon',
                'version' => $doc->version ?? 'v1.0',
                'is_active' => (bool) $doc->is_active,
                'chunks_count' => (int) ($firstChunkMetadata['rag_chunks_count'] ?? $doc->chunks_count),
                'created_at' => $doc->created_at?->translatedFormat('d M Y, H:i') . ' WIB',
                'updated_at' => $doc->updated_at?->translatedFormat('d M Y, H:i') . ' WIB',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    /**
     * Store a newly created knowledge document and auto-generate vector chunks.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'in:PUBLIC_SERVICE,UMKM,WASTE_EDUCATION'],
            'source' => ['nullable', 'string', 'max:255'],
            'validator' => ['nullable', 'string', 'max:100'],
            'version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'content' => ['nullable', 'string'],
            'chunks' => ['nullable', 'array'],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,xlsx,xls,txt', 'max:51200'],
        ]);

        if (!$request->hasFile('file') && empty($validated['content']) && empty($validated['chunks'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unggah file atau isi konten teks dokumen terlebih dahulu.',
            ], 422);
        }

        $document = KbDocument::create([
            'title' => $validated['title'],
            'domain' => ServiceDomain::from($validated['domain']),
            'source' => $validated['source'] ?? $request->file('file')?->getClientOriginalName() ?? 'Upload SOP',
            'validator' => $validated['validator'] ?? 'Aparatur Pekon',
            'version' => $validated['version'] ?? 'v1.0',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $contentToChunk = $validated['content'] ?? ($validated['chunks'] ?? []);
        $chunksCount = 0;
        $ragDocumentId = null;

        try {
            if ($request->hasFile('file')) {
                $ragUpload = $this->ragGateway->uploadDocument(
                    $request->file('file'),
                    $this->ragMetadata($document)
                );

                $ragDocumentId = $ragUpload['document_id'] ?? null;
                $chunksCount = (int) ($ragUpload['chunks'] ?? 0);
                $this->saveRagSyncChunk($document, $request->file('file')->getClientOriginalName(), $chunksCount, $ragDocumentId);
            } elseif (!empty($contentToChunk)) {
                $ragUpload = $this->ragGateway->indexText(array_merge(
                    $this->ragMetadata($document),
                    ['content' => is_array($contentToChunk) ? implode("\n\n", $contentToChunk) : $contentToChunk]
                ));

                $ragDocumentId = $ragUpload['document_id'] ?? null;
                $chunksCount = (int) ($ragUpload['chunks'] ?? 0);
                $this->saveRagSyncChunk($document, $document->title.'.txt', $chunksCount, $ragDocumentId);
            }
        } catch (Throwable $e) {
            $document->delete();

            Log::warning('Python RAG document indexing failed', [
                'title' => $validated['title'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Dokumen gagal diindeks ke Python RAG. Pastikan layanan ChatBot berjalan di http://127.0.0.1:8001.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        // Activity Audit Log
        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'VECTOR_DOC_CREATE',
            'target' => '#DOC-' . str_pad($document->document_id, 3, '0', STR_PAD_LEFT),
            'description' => "Menambahkan dokumen SOP/Knowledge Base: '{$document->title}' ({$chunksCount} chunks vektor dibuat)",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen pengetahuan berhasil ditambahkan dan ' . $chunksCount . ' chunk vektor telah diindeks.',
            'data' => [
                'document_id' => $document->document_id,
                'title' => $document->title,
                'chunks_count' => $chunksCount,
                'rag_document_id' => $ragDocumentId,
            ],
        ], 201);
    }

    private function ragMetadata(KbDocument $document): array
    {
        return [
            'title' => $document->title,
            'domain' => $document->domain instanceof ServiceDomain ? $document->domain->value : (string) $document->domain,
            'source' => $document->source,
            'validator' => $document->validator ?? 'Aparatur Pekon',
            'version' => $document->version ?? 'v1.0',
            'is_active' => (bool) $document->is_active,
            'laravel_document_id' => $document->document_id,
        ];
    }

    private function saveRagSyncChunk(
        KbDocument $document,
        string $fileName,
        int $chunksCount,
        int|string|null $ragDocumentId
    ): void {
        $document->chunks()->delete();

        KbChunk::create([
            'document_id' => $document->document_id,
            'chunk_index' => 0,
            'content' => "File {$fileName} telah diindeks oleh Python RAG ({$chunksCount} chunks).",
            'embedding' => null,
            'metadata' => array_merge($this->ragMetadata($document), [
                'file_name' => $fileName,
                'rag_document_id' => $ragDocumentId,
                'rag_chunks_count' => $chunksCount,
                'indexed_at' => now()->toIso8601String(),
            ]),
            'created_at' => now(),
        ]);
    }

    /**
     * Display the specified knowledge document and all its chunks.
     */
    public function show(string|int $id): JsonResponse
    {
        $document = KbDocument::with(['chunks' => function ($q) {
            $q->orderBy('chunk_index', 'asc');
        }])->findOrFail($id);

        $chunks = $document->chunks->map(function (KbChunk $chunk) {
            return [
                'chunk_id' => $chunk->chunk_id,
                'chunk_index' => $chunk->chunk_index,
                'content' => $chunk->content,
                'char_count' => mb_strlen($chunk->content),
                'word_count' => str_word_count($chunk->content),
                'embedding_sample' => json_decode($chunk->embedding ?? '[]'),
                'metadata' => $chunk->metadata,
                'created_at' => $chunk->created_at?->translatedFormat('d M Y, H:i') . ' WIB',
            ];
        });
        $firstChunkMetadata = $document->chunks->first()?->metadata ?? [];

        return response()->json([
            'status' => 'success',
            'data' => [
                'document_id' => $document->document_id,
                'doc_code' => 'DOC-' . str_pad($document->document_id, 3, '0', STR_PAD_LEFT),
                'title' => $document->title,
                'domain' => $document->domain instanceof ServiceDomain ? $document->domain->value : (string) $document->domain,
                'source' => $document->source,
                'validator' => $document->validator,
                'version' => $document->version,
                'is_active' => (bool) $document->is_active,
                'chunks_count' => (int) ($firstChunkMetadata['rag_chunks_count'] ?? $chunks->count()),
                'chunks' => $chunks,
                'created_at' => $document->created_at?->translatedFormat('d M Y, H:i') . ' WIB',
                'updated_at' => $document->updated_at?->translatedFormat('d M Y, H:i') . ' WIB',
            ],
        ]);
    }

    /**
     * Update the specified knowledge document and re-chunk content if provided.
     */
    public function update(Request $request, string|int $id): JsonResponse
    {
        $document = KbDocument::findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'in:PUBLIC_SERVICE,UMKM,WASTE_EDUCATION'],
            'source' => ['nullable', 'string', 'max:255'],
            'validator' => ['nullable', 'string', 'max:100'],
            'version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'content' => ['nullable', 'string'],
            'chunks' => ['nullable', 'array'],
        ]);

        $document->update([
            'title' => $validated['title'],
            'domain' => ServiceDomain::from($validated['domain']),
            'source' => $validated['source'] ?? $document->source,
            'validator' => $validated['validator'] ?? $document->validator,
            'version' => $validated['version'] ?? $document->version,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $document->is_active,
        ]);

        $chunksCount = $document->chunks()->count();

        // If new content or raw chunks are supplied, re-generate chunks
        if (isset($validated['content']) || isset($validated['chunks'])) {
            $contentToChunk = $validated['content'] ?? ($validated['chunks'] ?? []);
            $chunksCount = $this->chunkingService->processAndSaveChunks($document, $contentToChunk);
        }

        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'VECTOR_DOC_UPDATE',
            'target' => '#DOC-' . str_pad($document->document_id, 3, '0', STR_PAD_LEFT),
            'description' => "Memperbarui dokumen pengetahuan: '{$document->title}'",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen pengetahuan berhasil diperbarui.',
            'data' => [
                'document_id' => $document->document_id,
                'title' => $document->title,
                'chunks_count' => $chunksCount,
            ],
        ]);
    }

    /**
     * Remove the specified knowledge document and all its chunks.
     */
    public function destroy(Request $request, string|int $id): JsonResponse
    {
        $document = KbDocument::with('chunks')->findOrFail($id);
        $title = $document->title;
        $docId = $document->document_id;
        $ragDocumentId = $document->chunks->first()?->metadata['rag_document_id'] ?? null;

        if ($ragDocumentId) {
            try {
                $this->ragGateway->deleteDocument((int) $ragDocumentId);
            } catch (Throwable $e) {
                Log::debug('Python RAG document delete skipped', [
                    'rag_document_id' => $ragDocumentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $document->chunks()->delete();
        $document->delete();

        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'VECTOR_DOC_DELETE',
            'target' => '#DOC-' . str_pad($docId, 3, '0', STR_PAD_LEFT),
            'description' => "Menghapus dokumen pengetahuan: '{$title}' dan seluruh potongan vektornya",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen dan seluruh chunk vektor terkait berhasil dihapus.',
        ]);
    }

    /**
     * Re-index chunks for a specific document.
     */
    public function reindex(Request $request, string|int $id): JsonResponse
    {
        $document = KbDocument::with('chunks')->findOrFail($id);
        $existingChunks = $document->chunks->pluck('content')->toArray();

        $count = $this->chunkingService->processAndSaveChunks($document, $existingChunks);

        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'VECTOR_REINDEX',
            'target' => '#DOC-' . str_pad($document->document_id, 3, '0', STR_PAD_LEFT),
            'description' => "Melakukan re-indexing vektor untuk dokumen: '{$document->title}' ({$count} chunks)",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Indeks vektor berhasil diperbarui untuk dokumen ini (' . $count . ' chunks).',
        ]);
    }

    /**
     * Re-index all active knowledge documents using lazyById batching.
     */
    public function reindexAll(Request $request): JsonResponse
    {
        $totalChunks = 0;
        $totalDocs = 0;

        // Stream batch per 20 documents using PHP Generators
        KbDocument::where('is_active', true)
            ->lazyById(20)
            ->each(function (KbDocument $doc) use (&$totalChunks, &$totalDocs) {
                $chunkTexts = $doc->chunks()->pluck('content')->toArray();
                if (!empty($chunkTexts)) {
                    $totalChunks += $this->chunkingService->processAndSaveChunks($doc, $chunkTexts);
                }
                $totalDocs++;
            });

        $operator = $request->user();
        ActivityLog::create([
            'operator_id' => $operator?->operator_id,
            'actor_name' => $operator?->name ?? 'Aparatur Pekon',
            'action' => 'VECTOR_REINDEX_ALL',
            'target' => '#ALL-DOCS',
            'description' => "Sinkronisasi & re-indexing menyeluruh seluruh basis data vektor ({$totalDocs} dokumen, {$totalChunks} chunks)",
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Sinkronisasi ulang basis pengetahuan selesai. {$totalDocs} dokumen diproses, {$totalChunks} chunks vektor terindeks.",
            'data' => [
                'total_documents' => $totalDocs,
                'total_chunks' => $totalChunks,
            ],
        ]);
    }

    /**
     * Simulate semantic search retrieval across all active knowledge chunks.
     */
    public function testRetrieval(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:10'],
            'domain' => ['nullable', 'string'],
        ]);

        $query = trim($request->input('query'));
        $topK = (int) $request->input('top_k', 3);
        $domain = $request->input('domain');

        try {
            $ragResponse = $this->ragGateway->search($query, $topK);
            $sources = collect($ragResponse['sources'] ?? []);

            if ($domain && strtoupper($domain) !== 'ALL') {
                $sources = $sources->filter(function ($source) use ($domain) {
                    return strtoupper($source['metadata']['domain'] ?? '') === strtoupper($domain);
                });
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'query' => $query,
                    'top_k' => $topK,
                    'total_candidates' => $sources->count(),
                    'matched_count' => $sources->count(),
                    'execution_time' => 'Python RAG',
                    'results' => $sources->values()->map(function ($source, $index) {
                        $metadata = $source['metadata'] ?? [];
                        $distance = isset($source['distance']) ? (float) $source['distance'] : null;
                        $similarity = $distance === null ? null : max(0, min(1, 1 - $distance));

                        return [
                            'chunk_id' => $index + 1,
                            'chunk_index' => $metadata['chunk_in_record'] ?? $index,
                            'document_id' => $metadata['laravel_document_id'] ?? null,
                            'document_title' => $metadata['title'] ?? $source['file_name'] ?? 'Dokumen RAG',
                            'domain' => $metadata['domain'] ?? 'RAG',
                            'validator' => $metadata['validator'] ?? 'Aparatur Pekon',
                            'source' => $metadata['source'] ?? ($source['file_name'] ?? '-'),
                            'content' => $source['content'] ?? '',
                            'similarity' => $similarity,
                            'similarity_percentage' => $similarity === null ? null : round($similarity * 100, 1).'%',
                            'char_count' => mb_strlen($source['content'] ?? ''),
                            'metadata' => $metadata,
                        ];
                    }),
                ],
            ]);
        } catch (Throwable $e) {
            Log::debug('Python RAG retrieval unavailable, falling back to local simulator', [
                'error' => $e->getMessage(),
            ]);
        }

        $retrievalResult = $this->chunkingService->testRetrieval($query, $topK, $domain);

        return response()->json([
            'status' => 'success',
            'data' => $retrievalResult,
        ]);
    }

    /**
     * Update an individual chunk content.
     */
    public function updateChunk(Request $request, int $chunkId): JsonResponse
    {
        $chunk = KbChunk::with('document')->findOrFail($chunkId);
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $chunk->content = trim($validated['content']);
        $chunk->metadata = array_merge($chunk->metadata ?? [], [
            'char_count' => mb_strlen($chunk->content),
            'word_count' => str_word_count($chunk->content),
            'updated_at' => now()->toIso8601String(),
        ]);
        $chunk->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Potongan teks chunk berhasil diperbarui.',
            'data' => [
                'chunk_id' => $chunk->chunk_id,
                'content' => $chunk->content,
            ],
        ]);
    }

    /**
     * Delete an individual chunk.
     */
    public function deleteChunk(int $chunkId): JsonResponse
    {
        $chunk = KbChunk::findOrFail($chunkId);
        $docId = $chunk->document_id;
        $chunk->delete();

        // Re-index remaining chunks index order
        $remaining = KbChunk::where('document_id', $docId)->orderBy('chunk_index')->get();
        foreach ($remaining as $idx => $remChunk) {
            $remChunk->chunk_index = $idx;
            $remChunk->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Chunk berhasil dihapus.',
        ]);
    }
}

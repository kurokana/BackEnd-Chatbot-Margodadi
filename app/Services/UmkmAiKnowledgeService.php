<?php

namespace App\Services;

use App\Enums\ServiceDomain;
use App\Models\KbChunk;
use App\Models\KbDocument;
use App\Models\Umkm;
use Illuminate\Support\Facades\Log;
use Throwable;

class UmkmAiKnowledgeService
{
    public function __construct(
        protected RagGatewayService $ragGateway
    ) {}

    /**
     * Generate rich markdown knowledge text for an UMKM.
     */
    public function buildMarkdownProfile(Umkm $umkm): string
    {
        $umkm->loadMissing(['category', 'products']);

        $categoryName = $umkm->category?->name ?? 'UMKM Mandiri Desa';
        $products = $umkm->products->where('is_active', true);

        $productLines = [];
        if ($products->isNotEmpty()) {
            foreach ($products as $prod) {
                $priceFormatted = is_numeric($prod->price)
                    ? 'Rp ' . number_format((float) $prod->price, 0, ',', '.')
                    : ($prod->price ?: 'Hubungi Penjual');
                
                $desc = $prod->description ? " - {$prod->description}" : '';
                $productLines[] = "- **{$prod->name}** (Harga: {$priceFormatted}){$desc}";
            }
        } else {
            $productLines[] = '- Belum ada rincian produk khusus yang didaftarkan.';
        }

        $productListString = implode("\n", $productLines);

        $waContact = $umkm->wa_number ?: ($umkm->phone ?: '-');
        $locationNotes = array_filter([
            $umkm->address,
            $umkm->group_location,
            'Pekon Margodadi, Kecamatan Ambarawa, Kabupaten Pringsewu',
        ]);
        $fullLocation = implode(', ', $locationNotes);

        return <<<MARKDOWN
# Direktori Potensi UMKM Pekon Margodadi: {$umkm->name}
- Nomor Registrasi Pekon: {$umkm->reg_number}
- Slogan / Fokus Usaha: {$umkm->sub_title}
- Kategori Usaha: {$categoryName}
- Nama Pemilik / Pengelola: {$umkm->owner_name}
- Kontak WhatsApp Resmi: {$waContact}
- Nomor Telepon: {$umkm->phone}
- Alamat & Lokasi Produksi: {$fullLocation}
- Penanda Titik Lokasi Peta: {$umkm->map_title} ({$umkm->map_address})
- Tautan Google Maps: {$umkm->map_url}
- Legalitas & Sertifikasi Resmi: {$umkm->legal_certification} (Nomor: {$umkm->legal_number})
- Kapasitas Produksi: {$umkm->production_capacity} ({$umkm->capacity_note})
- Kelompok Binaan / Paguyuban: {$umkm->group_name} ({$umkm->group_location})

## Deskripsi & Profil Usaha:
{$umkm->description}

## Sejarah & Latar Belakang Usaha:
{$umkm->history}

## Daftar Produk & Layanan Usaha:
{$productListString}

## Panduan Pembelian & Pemesanan:
Masyarakat, wisatawan, atau calon pembeli dapat memesan dan membeli produk dari {$umkm->name} langsung ke alamat gerai/produksi di {$umkm->address} atau menghubungi kontak WhatsApp resmi pemilik di {$waContact}. Seluruh produk UMKM ini merupakan bagian dari potensi ekonomi binaan Pemerintahan Pekon Margodadi.
MARKDOWN;
    }

    /**
     * Synchronize a single UMKM to the Python RAG Vector Store and Knowledge Base.
     */
    public function syncToRag(Umkm $umkm): array
    {
        $markdownContent = $this->buildMarkdownProfile($umkm);

        // Find or create linked KbDocument
        $kbDoc = null;
        if ($umkm->kb_document_id) {
            $kbDoc = KbDocument::find($umkm->kb_document_id);
        }

        if (!$kbDoc) {
            $kbDoc = KbDocument::where('source', 'UMKM-' . $umkm->umkm_id)->first();
        }

        if (!$kbDoc) {
            $kbDoc = KbDocument::create([
                'title' => 'Katalog UMKM: ' . $umkm->name,
                'domain' => ServiceDomain::UMKM,
                'source' => 'UMKM-' . $umkm->umkm_id,
                'validator' => 'Aparatur Pekon Margodadi',
                'version' => 'v1.0',
                'is_active' => (bool) $umkm->is_active,
            ]);
        } else {
            $kbDoc->update([
                'title' => 'Katalog UMKM: ' . $umkm->name,
                'domain' => ServiceDomain::UMKM,
                'is_active' => (bool) $umkm->is_active,
            ]);
        }

        $ragDocId = $umkm->rag_document_id;
        $chunksCount = 0;

        try {
            // Delete old vector document in Python RAG if previously indexed
            if ($ragDocId) {
                try {
                    $this->ragGateway->deleteDocument((int) $ragDocId);
                } catch (Throwable $delEx) {
                    Log::debug('Old RAG document deletion skipped/failed', [
                        'doc_id' => $ragDocId,
                        'error' => $delEx->getMessage(),
                    ]);
                }
            }

            // Index new text representation
            $payload = [
                'title' => 'Katalog UMKM: ' . $umkm->name,
                'content' => $markdownContent,
                'domain' => ServiceDomain::UMKM->value,
                'source' => 'UMKM-' . $umkm->umkm_id,
                'validator' => 'Aparatur Pekon Margodadi',
                'version' => 'v1.0',
                'is_active' => (bool) $umkm->is_active,
                'laravel_document_id' => $kbDoc->document_id,
            ];

            $ragResult = $this->ragGateway->indexText($payload);
            $ragDocId = $ragResult['document_id'] ?? null;
            $chunksCount = (int) ($ragResult['chunks'] ?? 0);

            // Re-create chunk in Laravel KbChunk for metadata tracking
            $kbDoc->chunks()->delete();
            KbChunk::create([
                'document_id' => $kbDoc->document_id,
                'chunk_index' => 0,
                'content' => "Profil UMKM {$umkm->name} diindeks otomatis ke Python RAG ({$chunksCount} chunks).",
                'embedding' => null,
                'metadata' => [
                    'title' => $kbDoc->title,
                    'umkm_id' => $umkm->umkm_id,
                    'rag_document_id' => $ragDocId,
                    'rag_chunks_count' => $chunksCount,
                    'indexed_at' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);

            // Save reference on UMKM model
            $umkm->updateQuietly([
                'kb_document_id' => $kbDoc->document_id,
                'rag_document_id' => $ragDocId,
                'ai_synced_at' => now(),
            ]);

            return [
                'status' => 'success',
                'rag_document_id' => $ragDocId,
                'chunks_count' => $chunksCount,
                'ai_synced_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::warning('Auto-sync UMKM to Python RAG failed (service may be offline)', [
                'umkm_id' => $umkm->umkm_id,
                'name' => $umkm->name,
                'error' => $e->getMessage(),
            ]);

            // Keep kb_document_id linked even if Python RAG service is offline
            $umkm->updateQuietly([
                'kb_document_id' => $kbDoc->document_id,
            ]);

            return [
                'status' => 'warning',
                'message' => 'Data UMKM tersimpan di database, tetapi sinkronisasi AI tertunda: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete an UMKM from the Python RAG Vector Store and Knowledge Base.
     */
    public function deleteFromRag(Umkm $umkm): bool
    {
        try {
            if ($umkm->rag_document_id) {
                try {
                    $this->ragGateway->deleteDocument((int) $umkm->rag_document_id);
                } catch (Throwable $e) {
                    Log::debug('Failed to delete Python RAG document', ['error' => $e->getMessage()]);
                }
            }

            if ($umkm->kb_document_id) {
                $kbDoc = KbDocument::find($umkm->kb_document_id);
                if ($kbDoc) {
                    $kbDoc->chunks()->delete();
                    $kbDoc->delete();
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Error during UMKM deletion from RAG', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Synchronize all active UMKMs to Python RAG.
     */
    public function syncAll(): array
    {
        $umkms = Umkm::with(['category', 'products'])->where('is_active', true)->get();
        $total = $umkms->count();
        $success = 0;
        $failed = 0;
        $totalChunks = 0;

        foreach ($umkms as $umkm) {
            $result = $this->syncToRag($umkm);
            if (($result['status'] ?? '') === 'success') {
                $success++;
                $totalChunks += (int) ($result['chunks_count'] ?? 0);
            } else {
                $failed++;
            }
        }

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'total_chunks' => $totalChunks,
        ];
    }
}

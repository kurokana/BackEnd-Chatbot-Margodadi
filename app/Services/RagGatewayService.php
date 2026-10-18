<?php

namespace App\Services;

use App\Models\Umkm;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class RagGatewayService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.rag.base_url'), '/');
    }

    public function chat(string $question, int $topK = 3, ?string $sessionId = null): array
    {
        return $this->postJson('/chat', [
            'question' => $question,
            'top_k' => $topK,
            'session_id' => $sessionId,
            // The directory comes from current published records, independently of vector indexing.
            'umkm_catalogue' => Umkm::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('umkm_id')
                ->get(['umkm_id', 'name'])
                ->map(fn (Umkm $umkm) => ['id' => $umkm->umkm_id, 'name' => $umkm->name])
                ->all(),
        ], (int) config('services.rag.chat_timeout', 240))->json();
    }

    public function search(string $question, int $topK = 3): array
    {
        return $this->postJson('/search', [
            'question' => $question,
            'top_k' => $topK,
        ], (int) config('services.rag.search_timeout', 60))->json();
    }

    public function documents(): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->get($this->url('/documents'));

        $response->throw();
        return $response->json();
    }

    public function uploadDocument(UploadedFile $file, array $metadata): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        try {
            $response = Http::timeout(300)
                ->acceptJson()
                ->attach('file', $handle, $file->getClientOriginalName())
                ->post($this->url('/documents/upload'), $metadata);

            $response->throw();
            return $response->json();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function indexText(array $payload, ?int $timeout = null): array
    {
        $timeout = $timeout ?? min((int) config('services.rag.index_timeout', 60), 30);
        return $this->postJson('/documents/text', $payload, $timeout)->json();
    }

    public function deleteDocument(int $documentId): bool
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->delete($this->url('/documents/'.$documentId));

        $response->throw();
        return (bool) ($response->json('deleted') ?? false);
    }

    private function postJson(string $path, array $payload, int $timeout): Response
    {
        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($this->url($path), $payload);

        $response->throw();
        return $response;
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }
}

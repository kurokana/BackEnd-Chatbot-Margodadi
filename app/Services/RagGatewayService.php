<?php

namespace App\Services;

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

    public function chat(string $question, int $topK = 3): array
    {
        return $this->postJson('/chat', [
            'question' => $question,
            'top_k' => $topK,
        ], 120)->json();
    }

    public function search(string $question, int $topK = 3): array
    {
        return $this->postJson('/search', [
            'question' => $question,
            'top_k' => $topK,
        ], 60)->json();
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

    public function indexText(array $payload): array
    {
        return $this->postJson('/documents/text', $payload, 120)->json();
    }

    public function deleteDocument(int $documentId): bool
    {
        $response = Http::timeout(30)
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

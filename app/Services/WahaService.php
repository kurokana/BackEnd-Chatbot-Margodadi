<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaService
{
    private string $baseUrl;
    private string $session;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.waha.base_url', 'http://127.0.0.1:3000'), '/');
        $this->session = (string) config('services.waha.session', 'default');
        $this->apiKey = config('services.waha.api_key');
    }

    /**
     * Kirim pesan teks ke nomor WhatsApp via WAHA.
     */
    public function sendMessage(string $recipient, string $text): array
    {
        $chatId = $this->formatChatId($recipient);

        $url = "{$this->baseUrl}/api/sendText";
        $payload = [
            'session' => $this->session,
            'chatId' => $chatId,
            'text' => $text,
        ];

        $request = Http::timeout(30)->acceptJson();
        if ($this->apiKey) {
            $request->withHeaders(['X-Api-Key' => $this->apiKey]);
        }

        $response = $request->post($url, $payload);

        if (! $response->successful()) {
            Log::error('WAHA sendText failed', [
                'recipient' => $recipient,
                'chatId' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Gagal mengirim pesan WhatsApp: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Cek status sesi WAHA (WORKING, SCAN_QR_CODE, STOPPED, dll).
     */
    public function getSessionStatus(): array
    {
        $url = "{$this->baseUrl}/api/sessions/{$this->session}";

        $request = Http::timeout(10)->acceptJson();
        if ($this->apiKey) {
            $request->withHeaders(['X-Api-Key' => $this->apiKey]);
        }

        $response = $request->get($url);

        return $response->json() ?? [];
    }

    /**
     * Dapatkan detail kontak WAHA berdasarkan JID/LID/nomor.
     */
    public function getContact(string $contactId): ?array
    {
        $url = "{$this->baseUrl}/api/contacts";

        $request = Http::timeout(10)->acceptJson();
        if ($this->apiKey) {
            $request->withHeaders(['X-Api-Key' => $this->apiKey]);
        }

        $response = $request->get($url, [
            'session' => $this->session,
            'contactId' => $contactId,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Format nomor HP Indonesia menjadi format JID WhatsApp (misal: 628123456789@c.us atau 123...@lid).
     */
    public function formatChatId(string $phone): string
    {
        // Jika sudah memiliki suffix @c.us, @lid, atau @g.us
        if (str_contains($phone, '@')) {
            return trim($phone);
        }

        // Hapus karakter non-digit
        $digits = preg_replace('/\D+/', '', $phone);

        // Jika diawali 08 -> ganti jadi 628
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        // Jika ini adalah WhatsApp LID (biasanya >13 digit dan bukan berawalan kode negara 62)
        if (strlen($digits) > 13 && ! str_starts_with($digits, '62')) {
            return $digits.'@lid';
        }

        return $digits.'@c.us';
    }
}

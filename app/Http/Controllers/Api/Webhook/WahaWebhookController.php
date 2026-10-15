<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\HitlEventType;
use App\Enums\PriorityLevel;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\HitlEvent;
use App\Models\Message;
use App\Models\Operator;
use App\Models\User;
use App\Services\RagGatewayService;
use App\Services\WahaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WahaWebhookController extends Controller
{
    public function __construct(
        protected WahaService $wahaService,
        protected RagGatewayService $ragGateway
    ) {}

    /**
     * Handle incoming webhook event from WAHA (WhatsApp HTTP API).
     */
    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $payload = $request->input('payload', []);

        Log::info('WAHA webhook received', [
            'event' => $event,
            'from' => $payload['from'] ?? null,
            'fromMe' => $payload['fromMe'] ?? null,
        ]);

        // Hanya proses event pesan masuk (bukan pesan keluar dari bot sendiri)
        if (! in_array($event, ['message', 'message.any'])) {
            return response()->json(['status' => 'ignored', 'reason' => 'non-message event']);
        }

        if (! empty($payload['fromMe'])) {
            return response()->json(['status' => 'ignored', 'reason' => 'outgoing message']);
        }

        $from = $payload['from'] ?? '';
        $body = trim($payload['body'] ?? '');

        // Abaikan chat grup (@g.us) atau pesan kosong
        if (str_contains($from, '@g.us') || empty($body)) {
            return response()->json(['status' => 'ignored', 'reason' => 'group or empty body']);
        }

        // Ekstrak nomor telepon & chatId tujuan balasan
        $replyChatId = $from;
        $cleanPhone = explode('@', $from)[0];
        $senderName = $payload['_data']['notifyName']
            ?? $payload['pushName']
            ?? null;

        // Jika pengirim menggunakan WhatsApp privacy/LID (@lid), dapatkan nomor HP dan nama asli dari kontak WAHA
        if (str_ends_with($from, '@lid')) {
            $contact = $this->wahaService->getContact($from);
            if (! empty($contact['id']) && str_ends_with($contact['id'], '@c.us')) {
                $cleanPhone = explode('@', $contact['id'])[0];
                $replyChatId = $contact['id'];
            }
            if (empty($senderName)) {
                $senderName = $contact['pushname'] ?? $contact['name'] ?? null;
            }
        }

        $senderName = $senderName ?: ('Warga WA (+'.$cleanPhone.')');

        // 1. Temukan atau buat User warga berdasarkan nomor WhatsApp
        $user = User::firstOrCreate(
            ['phone_number' => $cleanPhone],
            ['anonymous_code' => 'WA-'.substr($cleanPhone, -4)]
        );

        // 2. Temukan percakapan aktif yang belum selesai, atau buat baru
        $conversation = Conversation::where('user_id', $user->user_id)
            ->where('channel', ChannelType::WHATSAPP)
            ->where('status', '!=', ConversationStatus::RESOLVED)
            ->latest('updated_at')
            ->first();

        if (! $conversation) {
            $sessionId = 'WA-'.$cleanPhone.'-'.now()->format('YmdHis');
            $conversation = Conversation::create([
                'user_id' => $user->user_id,
                'external_conversation_id' => $sessionId,
                'channel' => ChannelType::WHATSAPP,
                'status' => ConversationStatus::OPEN,
                'needs_human' => false,
                'priority' => PriorityLevel::MEDIUM,
                'citizen_name' => $senderName,
                'started_at' => now(),
                'last_message_at' => now(),
            ]);

            ActivityLog::create([
                'operator_id' => null,
                'actor_name' => $senderName,
                'action' => 'CHAT_SESSION_START',
                'target' => '#'.$sessionId,
                'description' => "Warga memulai percakapan baru via WhatsApp: {$cleanPhone}",
                'channel' => ChannelType::WHATSAPP,
                'created_at' => now(),
            ]);
        }

        // 3. Simpan pesan dari warga ke tabel messages
        Message::create([
            'conversation_id' => $conversation->conversation_id,
            'sender_type' => SenderType::USER,
            'content' => $body,
            'external_message_id' => $payload['id'] ?? null,
            'created_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'citizen_name' => $senderName,
        ]);

        // 4. Jika tiket ini sudah diambil alih oleh operator (HITL aktif), jangan dibalas oleh bot otomatis
        if ($conversation->needs_human && $conversation->status === ConversationStatus::ASSIGNED) {
            Log::info('WhatsApp message skipped auto-reply because conversation is in HITL mode', [
                'conversation_id' => $conversation->conversation_id,
                'operator_id' => $conversation->assigned_operator_id,
            ]);

            return response()->json([
                'status' => 'success',
                'mode' => 'human_operator_active',
                'conversation_id' => $conversation->conversation_id,
            ]);
        }

        // 5. Cek apakah pesan warga meminta bantuan petugas (Human-in-the-Loop)
        $q = strtolower($body);
        $hitlKeywords = ['operator', 'manusia', 'petugas', 'kadus', 'lurah', 'bantuan langsung', 'tidak paham', 'salah', 'komplain', 'dinas luar', 'mendesak', 'diskresi'];
        $needsHuman = false;

        foreach ($hitlKeywords as $kw) {
            if (str_contains($q, $kw)) {
                $needsHuman = true;
                break;
            }
        }

        if ($needsHuman) {
            $operator = Operator::where('is_active', true)->where('role', 'OPERATOR')->first()
                ?: Operator::where('is_active', true)->first();

            $conversation->update([
                'needs_human' => true,
                'status' => ConversationStatus::ASSIGNED,
                'priority' => PriorityLevel::HIGH,
                'assigned_operator_id' => $operator?->operator_id,
                'last_message_at' => now(),
            ]);

            HitlEvent::create([
                'conversation_id' => $conversation->conversation_id,
                'operator_id' => $operator?->operator_id,
                'event_type' => HitlEventType::NEED_HUMAN,
                'notes' => 'Permintaan bantuan operator WhatsApp oleh warga: '.$body,
                'created_at' => now(),
            ]);

            ActivityLog::create([
                'operator_id' => $operator?->operator_id,
                'action' => 'HITL_ESCALATION',
                'actor_name' => 'Sistem RAG Margodadi',
                'target' => $conversation->external_conversation_id ?? ('CV-'.$conversation->conversation_id),
                'channel' => ChannelType::WHATSAPP,
                'description' => 'Percakapan WhatsApp dialihkan ke operator: '.$body,
            ]);

            $escalationText = "Halo Bapak/Ibu {$senderName}, saya ".($operator?->name ?? 'Dewi Lestari')." dari Kasi Pelayanan Pekon Margodadi.\n\nPesan Anda telah diteruskan ke petugas piket kami. Anda juga dapat datang langsung ke Balai Pekon Margodadi pada jam kerja atau menunggu balasan dari operator di nomor ini.";

            Message::create([
                'conversation_id' => $conversation->conversation_id,
                'sender_type' => SenderType::OPERATOR,
                'operator_id' => $operator?->operator_id,
                'content' => $escalationText,
                'created_at' => now(),
            ]);

            try {
                $this->wahaService->sendMessage($replyChatId, $escalationText);
            } catch (Throwable $e) {
                Log::warning('Failed sending WAHA escalation reply', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'status' => 'success',
                'mode' => 'escalated_to_hitl',
                'conversation_id' => $conversation->conversation_id,
            ]);
        }

        // 6. Respon Otomatis menggunakan Python RAG
        try {
            $ragResponse = $this->ragGateway->chat($body, 3);
            $replyText = $ragResponse['answer'] ?? 'Maaf, informasi tersebut tidak tersedia dalam basis data layanan Pekon Margodadi.';
        } catch (Throwable $e) {
            Log::warning('Python RAG unavailable for WhatsApp chat', [
                'conversation_id' => $conversation->conversation_id,
                'error' => $e->getMessage(),
            ]);
            $replyText = 'Maaf, sistem layanan otomatis sedang dalam pemeliharaan. Pertanyaan Anda telah dicatat dan akan dibantu oleh petugas kami.';
        }

        // Simpan jawaban bot ke database
        Message::create([
            'conversation_id' => $conversation->conversation_id,
            'sender_type' => SenderType::BOT,
            'content' => $replyText,
            'created_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        // Kirimkan jawaban balik ke nomor WhatsApp warga via WAHA
        try {
            $this->wahaService->sendMessage($replyChatId, $replyText);
        } catch (Throwable $e) {
            Log::warning('Failed sending WAHA bot reply', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'status' => 'success',
            'mode' => 'bot_answered',
            'conversation_id' => $conversation->conversation_id,
        ]);
    }
}

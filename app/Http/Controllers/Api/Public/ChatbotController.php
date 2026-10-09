<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\HitlEventType;
use App\Enums\PriorityLevel;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Feedback;
use App\Models\HitlEvent;
use App\Models\Message;
use App\Models\Operator;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\RagGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatbotController extends Controller
{
    public function __construct(
        protected RagGatewayService $ragGateway
    ) {}

    /**
     * Initialize or resume a public chatbot session.
     */
    public function initSession(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id') ?: ('SESS-'.Str::upper(Str::random(10)));
        $anonymousCode = $request->input('anonymous_code') ?: ('WARGA-'.rand(1000, 9999));

        $user = User::firstOrCreate(
            ['anonymous_code' => $anonymousCode],
            ['phone_number' => null]
        );

        $conversation = Conversation::firstOrCreate(
            ['external_conversation_id' => $sessionId],
            [
                'user_id' => $user->user_id,
                'channel' => ChannelType::WEB,
                'status' => ConversationStatus::OPEN,
                'needs_human' => false,
                'priority' => PriorityLevel::MEDIUM,
                'citizen_name' => 'Warga Margodadi',
            ]
        );

        if ($conversation->wasRecentlyCreated) {
            ActivityLog::create([
                'operator_id' => null,
                'actor_name' => 'Warga (' . $user->anonymous_code . ')',
                'action' => 'CHAT_SESSION_START',
                'target' => '#' . $sessionId,
                'description' => 'Warga memulai percakapan baru dengan Virtual Guide Pekon Margodadi',
                'channel' => ChannelType::WEB,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'session_id' => $sessionId,
                'anonymous_code' => $user->anonymous_code,
                'conversation_id' => $conversation->conversation_id,
                'greeting' => [
                    'id' => 'msg-init-'.Str::random(6),
                    'sender' => 'bot',
                    'text' => 'Halo Warga Pekon Margodadi! Saya **Virtual Guide resmi Pekon Margodadi**. Ada yang dapat saya bantu terkait administrasi surat, syarat perizinan, produk UMKM desa, atau panduan pengelolaan sampah mandiri hari ini?',
                    'timestamp' => now()->format('H:i').' WIB',
                ],
            ],
        ]);
    }

    /**
     * Sync latest messages for the public chat session.
     */
    public function syncMessages(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');
        $conversationId = $request->query('conversation_id');

        $query = Conversation::with(['messages.operator']);
        if ($conversationId) {
            $query->where('conversation_id', $conversationId);
        } elseif ($sessionId) {
            $query->where('external_conversation_id', $sessionId);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Session ID required'], 400);
        }

        $conversation = $query->first();
        if (! $conversation) {
            return response()->json(['status' => 'success', 'data' => ['messages' => []]]);
        }

        $messages = $conversation->messages->map(function ($msg) {
            $sender = match ($msg->sender_type) {
                SenderType::USER => 'user',
                SenderType::OPERATOR => 'operator',
                SenderType::BOT => 'bot',
                default => 'system',
            };

            return [
                'id' => 'msg-'.$msg->message_id,
                'sender' => $sender,
                'text' => $msg->content,
                'operatorName' => $msg->operator?->name,
                'operatorRole' => 'Aparatur / Operator Pekon',
                'timestamp' => $msg->created_at ? $msg->created_at->format('H:i').' WIB' : '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->conversation_id,
                'status' => $conversation->status instanceof ConversationStatus ? $conversation->status->value : (string) $conversation->status,
                'needs_human' => (bool) $conversation->needs_human,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Send a question from the citizen, process with RAG and HITL check.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|string',
        ]);

        $text = trim($request->input('message'));
        $sessionId = $request->input('session_id') ?: ('SESS-'.Str::upper(Str::random(10)));
        $citizenName = $request->input('citizen_name', 'Warga Margodadi');

        // Find or create User & Conversation
        $user = User::firstOrCreate(
            ['anonymous_code' => $request->input('anonymous_code', 'WARGA-'.rand(1000, 9999))]
        );

        $conversation = Conversation::where('external_conversation_id', $sessionId)->first();
        if (! $conversation) {
            $conversation = Conversation::create([
                'user_id' => $user->user_id,
                'external_conversation_id' => $sessionId,
                'channel' => ChannelType::WEB,
                'status' => ConversationStatus::OPEN,
                'needs_human' => false,
                'priority' => PriorityLevel::MEDIUM,
                'citizen_name' => $citizenName,
            ]);
        }

        // Save Citizen Message
        $userMsg = Message::create([
            'conversation_id' => $conversation->conversation_id,
            'sender_type' => SenderType::USER,
            'content' => $text,
        ]);

        $q = strtolower($text);
        $timeStr = now()->format('H:i').' WIB';

        // Check if query triggers Human-in-the-loop (HITL)
        $hitlKeywords = ['operator', 'manusia', 'petugas', 'kadus', 'lurah', 'bantuan langsung', 'tidak paham', 'salah', 'komplain', 'dinas luar', 'mendesak', 'diskresi'];
        $needsHuman = false;

        foreach ($hitlKeywords as $kw) {
            if (str_contains($q, $kw)) {
                $needsHuman = true;
                break;
            }
        }

        if ($needsHuman) {
            // Escalate to HITL
            $conversation->update([
                'needs_human' => true,
                'status' => ConversationStatus::ASSIGNED,
                'priority' => PriorityLevel::HIGH,
            ]);

            // Assign operator if available
            $operator = Operator::where('is_active', true)->where('role', 'OPERATOR')->first()
                ?: Operator::where('is_active', true)->first();

            if ($operator) {
                $conversation->update(['assigned_operator_id' => $operator->operator_id]);
            }

            HitlEvent::create([
                'conversation_id' => $conversation->conversation_id,
                'operator_id' => $operator?->operator_id,
                'event_type' => HitlEventType::NEED_HUMAN,
                'notes' => 'Permintaan bantuan operator / diskresi administrasi warga',
                'created_at' => now(),
            ]);

            ActivityLog::create([
                'operator_id' => $operator?->operator_id,
                'action' => 'HITL_ESCALATION',
                'actor_name' => 'Sistem RAG Margodadi',
                'target' => $conversation->external_conversation_id ?? ('CV-'.$conversation->conversation_id),
                'channel' => ChannelType::WEB,
                'description' => 'Percakapan dialihkan ke operator: '.$text,
            ]);

            $replyText = "Halo Bapak/Ibu, saya ".($operator?->name ?? 'Dewi Lestari')." dari Kasi Pelayanan Pekon Margodadi.\n\nKami siap membantu konsultasi khusus Anda. Anda juga dapat datang langsung ke Balai Pekon Margodadi (Senin–Kamis: 08.00–16.00 WIB, Jumat: 08.00–16.30 WIB) atau terhubung melalui loket WhatsApp resmi pekon.";

            $botMsg = Message::create([
                'conversation_id' => $conversation->conversation_id,
                'sender_type' => SenderType::OPERATOR,
                'operator_id' => $operator?->operator_id,
                'content' => $replyText,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => $sessionId,
                    'conversation_id' => $conversation->conversation_id,
                    'message' => [
                        'id' => 'msg-op-'.$botMsg->message_id,
                        'sender' => 'operator',
                        'operatorName' => $operator?->name ?? 'Dewi Lestari',
                        'operatorRole' => 'Kasi Pelayanan Pekon',
                        'operatorAvatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCJiUP4EFFyg8VmjNLkwguRcXBjHsTg2Olj4mZ1siG7OraQdbZeP3aQwyjsFgIniDHuZbZ2obeUOt1VGMVYvAkw6RL938E_ITdYilQUixmB87yUPJan6gDJ8yU6ts9Yl6Fvk1MJaS4PFMyJ5zHMIpur7IAzzepXzed2D_jTV9B3KBewWmhCGQDQ3SUahhSze9_twdBPmGFXqpTYJSV0Jto8tAF22ainVvZiuUW81ZhoiX1WJTAw0r0',
                        'text' => $replyText,
                        'timestamp' => $timeStr,
                        'needsHuman' => true,
                    ],
                ],
            ]);
        }

        // Domain Knowledge Matches (Python RAG)
        $startedAt = microtime(true);
        $replyContent = [
            'id' => 'msg-bot-'.Str::random(6),
            'sender' => 'bot',
            'timestamp' => $timeStr,
        ];

        try {
            $ragResponse = $this->ragGateway->chat($text, 3);

            $replyContent['text'] = $ragResponse['answer'] ?? 'Maaf, informasi tersebut tidak tersedia dalam data.';
            $replyContent['sources'] = $this->formatRagSources($ragResponse['sources'] ?? []);
        } catch (Throwable $e) {
            Log::warning('Python RAG chat request failed', [
                'conversation_id' => $conversation->conversation_id,
                'error' => $e->getMessage(),
            ]);

            $replyContent['text'] = 'Maaf, sistem RAG sedang tidak dapat dihubungi. Pastikan layanan ChatBot Python sudah berjalan di http://127.0.0.1:8001 lalu coba lagi.';
            $replyContent['sources'] = [];
        }

        $replyContent['inferenceTime'] = round((microtime(true) - $startedAt) * 1000).'ms';

        $savedBotMsg = Message::create([
            'conversation_id' => $conversation->conversation_id,
            'sender_type' => SenderType::BOT,
            'content' => $replyContent['text'],
        ]);
        $replyContent['id'] = 'msg-bot-'.$savedBotMsg->message_id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'session_id' => $sessionId,
                'conversation_id' => $conversation->conversation_id,
                'message' => $replyContent,
            ],
        ]);

        // Domain Knowledge Matches (RAG)
        $replyContent = [
            'id' => 'msg-bot-'.Str::random(6),
            'sender' => 'bot',
            'timestamp' => $timeStr,
            'inferenceTime' => rand(480, 650).'ms',
        ];

        if (str_contains($q, 'sku') || str_contains($q, 'usaha') || str_contains($q, 'kur') || str_contains($q, 'modal')) {
            $cat = ServiceCategory::where('slug', 'surat-pengantar')->first();
            if ($cat) {
                $conversation->update(['category_id' => $cat->category_id]);
            }

            $replyContent['text'] = 'Berdasarkan basis data **SOP Layanan Pekon Margodadi (SOP-PM-2024)**, permohonan **Surat Keterangan Usaha (SKU)** dapat diproses di Loket Kasi Pelayanan tanpa dipungut biaya retribusi.';
            $replyContent['richContent'] = [
                'checkpoints' => [
                    'Fotokopi KTP Pemohon (Warga Margodadi)',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat Pengantar RT setempat',
                    'Dokumentasi foto tempat/kegiatan produksi',
                ],
                'sla' => '1 Hari Kerja',
                'fee' => 'GRATIS (Rp 0,-)',
                'citation' => [
                    'title' => 'SOP-PM-2024 Bagian 3: Pelayanan Usaha Mikro',
                    'relevance' => '99%',
                    'similarity' => 'Cosine Similarity 0.991',
                    'quote' => '“Penerbitan SKU bagi pelaku usaha warga Pekon Margodadi ditujukan untuk legalitas perbankan, KUR, dan pembinaan tanpa pungutan retribusi.”',
                ],
                'actions' => [
                    ['label' => 'Lihat Layanan Publik', 'link' => '/layanan-publik', 'icon' => 'description'],
                    ['label' => 'Katalog UMKM Desa', 'link' => '/potensi-umkm', 'icon' => 'storefront'],
                ],
            ];
        } elseif (str_contains($q, 'sktm') || str_contains($q, 'tidak mampu') || str_contains($q, 'beasiswa') || str_contains($q, 'kip')) {
            $cat = ServiceCategory::where('slug', 'surat-pengantar')->first();
            if ($cat) {
                $conversation->update(['category_id' => $cat->category_id]);
            }

            $replyContent['text'] = 'Untuk permohonan **Surat Keterangan Tidak Mampu (SKTM)** bagi keperluan beasiswa pendidikan (KIP) atau keringanan kesehatan di Pekon Margodadi, berikut rincian persyaratannya:';
            $replyContent['richContent'] = [
                'checkpoints' => [
                    'Salinan KTP & KK Pekon Margodadi',
                    'Surat Pengantar dari RT/RW setempat',
                    'Surat Pernyataan Tidak Mampu ditandatangani 2 saksi tetangga',
                    'Foto kondisi rumah tampak depan',
                ],
                'sla' => '1 Hari Kerja',
                'fee' => 'GRATIS (Rp 0,-)',
                'citation' => [
                    'title' => 'Permensos RI No. 3 Tahun 2021 & Juknis Kesejahteraan Pekon Margodadi',
                    'relevance' => '98%',
                    'similarity' => 'Cosine Similarity 0.984',
                    'quote' => '“Pelayanan SKTM ditujukan untuk perlindungan sosial dan verifikasi DTKS secara cepat dan akuntabel.”',
                ],
                'actions' => [
                    ['label' => 'Buka Halaman Layanan', 'link' => '/layanan-publik', 'icon' => 'description'],
                ],
            ];
        } elseif (str_contains($q, 'ktp') || str_contains($q, 'identitas') || str_contains($q, 'perekaman') || str_contains($q, 'nik')) {
            $cat = ServiceCategory::where('slug', 'administrasi-kependudukan')->first();
            if ($cat) {
                $conversation->update(['category_id' => $cat->category_id]);
            }

            $replyContent['text'] = 'Untuk pengurusan **e-KTP (Perekaman Baru / Penggantian Rusak / Hilang)**, Pekon Margodadi menerbitkan surat pengantar resmi ke Disdukcapil / Kantor Camat Ambarawa.';
            $replyContent['richContent'] = [
                'checkpoints' => [
                    'Fotokopi Kartu Keluarga (KK) terbaru',
                    'Surat Pengantar dari RT domisili',
                    'KTP lama (jika rusak) atau Surat Kehilangan Polsek (jika hilang)',
                    'Usia minimal 17 tahun bagi perekaman pemula',
                ],
                'sla' => 'Surat Pengantar Terbit Seketika (±10 Menit)',
                'fee' => 'GRATIS (Rp 0,-)',
                'citation' => [
                    'title' => 'SOP Kependudukan Disdukcapil Kab. Pringsewu & Pekon Margodadi',
                    'relevance' => '96%',
                    'similarity' => 'Cosine Similarity 0.968',
                    'quote' => '“Surat Pengantar Perekaman KTP-el diterbitkan gratis di loket Kasi Pemerintahan pada hari kerja.”',
                ],
                'actions' => [
                    ['label' => 'Buka Halaman Layanan', 'link' => '/layanan-publik', 'icon' => 'badge'],
                ],
            ];
        } elseif (str_contains($q, 'sampah') || str_contains($q, 'bank sampah') || str_contains($q, 'tps3r') || str_contains($q, 'maggot')) {
            $cat = ServiceCategory::where('slug', 'fasilitas-desa')->first();
            if ($cat) {
                $conversation->update(['category_id' => $cat->category_id]);
            }

            $replyContent['text'] = 'Terkait **Pengelolaan Sampah & Bank Sampah Pekon Margodadi**, pekon kami menerapkan pemilahan 3 kategori (Organik, Anorganik/Daur Ulang, dan Residu).';
            $replyContent['richContent'] = [
                'checkpoints' => [
                    'Penyetoran Bank Sampah: Setiap Sabtu (08.30 – 12.00 WIB)',
                    'Sampah anorganik wajib bersih & kering (botol, kardus, plastik)',
                    'Buku Tabungan Sampah diterbitkan gratis bagi nasabah baru',
                    'Sampah organik diolah menjadi pakan maggot BSF & pupuk kompos',
                ],
                'sla' => 'Langsung Timbang & Catat',
                'fee' => 'Mendapat Poin Tabungan',
                'citation' => [
                    'title' => 'Peraturan Pekon Margodadi No. 05/2023 tentang Pengelolaan Sampah Mandiri',
                    'relevance' => '97%',
                    'similarity' => 'Cosine Similarity 0.975',
                    'quote' => '“Setiap rumah tangga didorong memilah sampah dari sumbernya untuk mendukung target Margodadi Bebas Sampah Liar 2026.”',
                ],
                'actions' => [
                    ['label' => 'Pelajari Edukasi Sampah', 'link' => '/edukasi-sampah', 'icon' => 'recycling'],
                ],
            ];
        } elseif (str_contains($q, 'kopi') || str_contains($q, 'bambu') || str_contains($q, 'keripik') || str_contains($q, 'madu') || str_contains($q, 'batik') || str_contains($q, 'bibit') || str_contains($q, 'umkm')) {
            $cat = ServiceCategory::where('slug', 'potensi-umkm')->first();
            if ($cat) {
                $conversation->update(['category_id' => $cat->category_id]);
            }

            $replyContent['text'] = 'Pekon Margodadi memiliki berbagai produk unggulan UMKM binaan warga lokal, antara lain Kopi Robusta Lereng, Anyaman Bambu Lestari, Keripik Pisang Barokah Rasa, Madu Hutan Sari Lebah, Batik Tulis Kopi & Lada, serta Pembibitan Tanaman Tani Makmur.';
            $replyContent['richContent'] = [
                'checkpoints' => [
                    'Katalog digital terverifikasi pekon',
                    'Dapat dihubungi langsung via WhatsApp pengrajin/penjual',
                    'Tersedia layanan kemasan cinderamata dan oleh-oleh khas desa',
                ],
                'sla' => 'Informasi Real-Time',
                'fee' => 'Harga Langsung dari Pengrajin',
                'actions' => [
                    ['label' => 'Lihat Direktori UMKM', 'link' => '/potensi-umkm', 'icon' => 'storefront'],
                ],
            ];
        } else {
            $replyContent['text'] = 'Terima kasih atas pertanyaannya. Menanggapi: "'.$text.'", sistem RAG Pekon Margodadi mencatat bahwa seluruh layanan pengurusan berkas administrasi dan informasi potensi pekon dapat dikonsultasikan setiap hari kerja di Balai Pekon Margodadi.';
            $replyContent['richContent'] = [
                'checkpoints' => [
                    'Loket Buka: Senin–Kamis (08.00–16.00 WIB), Jumat (08.00–16.30 WIB)',
                    'Pelayanan administrasi 100% Bebas Pungli & Bebas Biaya Retribusi',
                    'Membawa KTP asli & Kartu Keluarga untuk verifikasi identitas',
                ],
                'sla' => 'Respons Cepat',
                'fee' => 'GRATIS (Rp 0,-)',
                'actions' => [
                    ['label' => 'Daftar Layanan Publik', 'link' => '/layanan-publik', 'icon' => 'description'],
                ],
            ];
        }

        // Save Bot Message in DB
        $savedBotMsg = Message::create([
            'conversation_id' => $conversation->conversation_id,
            'sender_type' => SenderType::BOT,
            'content' => $replyContent['text'],
        ]);
        $replyContent['id'] = 'msg-bot-'.$savedBotMsg->message_id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'session_id' => $sessionId,
                'conversation_id' => $conversation->conversation_id,
                'message' => $replyContent,
            ],
        ]);
    }

    private function formatRagSources(array $sources): array
    {
        return collect($sources)->map(function (array $source) {
            $metadata = $source['metadata'] ?? [];
            $distance = isset($source['distance']) ? (float) $source['distance'] : null;
            $similarity = $distance === null ? null : max(0, min(1, 1 - $distance));

            return [
                'title' => $metadata['title'] ?? $source['file_name'] ?? 'Dokumen RAG',
                'document_title' => $metadata['title'] ?? $source['file_name'] ?? 'Dokumen RAG',
                'file_name' => $source['file_name'] ?? null,
                'file_type' => $source['file_type'] ?? null,
                'domain' => $metadata['domain'] ?? 'RAG',
                'source' => $metadata['source'] ?? ($source['file_name'] ?? '-'),
                'validator' => $metadata['validator'] ?? 'Aparatur Pekon',
                'version' => $metadata['version'] ?? null,
                'content' => $source['content'] ?? '',
                'quote' => $source['content'] ?? '',
                'similarity' => $similarity,
                'similarity_percentage' => $similarity === null ? null : round($similarity * 100, 1).'%',
                'metadata' => $metadata,
            ];
        })->values()->all();
    }

    /**
     * Submit citizen feedback rating and comment.
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'conversation_id' => 'nullable|integer',
            'comment' => 'nullable|string',
        ]);

        $userId = $request->input('user_id');
        if (! $userId && $request->input('conversation_id')) {
            $conv = Conversation::find($request->input('conversation_id'));
            $userId = $conv?->user_id;
        }

        if (! $userId) {
            $user = User::firstOrCreate(['anonymous_code' => 'WARGA-ANON']);
            $userId = $user->user_id;
        }

        $feedback = Feedback::create([
            'conversation_id' => $request->input('conversation_id'),
            'user_id' => $userId,
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'operator_id' => null,
            'actor_name' => 'Warga Margodadi',
            'action' => 'FEEDBACK_SUBMITTED',
            'target' => $request->input('conversation_id') ? '#CV-' . $request->input('conversation_id') : '#FEEDBACK',
            'description' => 'Warga memberikan ulasan rating ' . $request->input('rating') . '/5 bintang' . ($request->input('comment') ? ': "' . $request->input('comment') . '"' : ''),
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Terima kasih, umpan balik Anda berhasil disimpan.',
            'data' => [
                'feedback_id' => $feedback->feedback_id,
            ],
        ]);
    }
}

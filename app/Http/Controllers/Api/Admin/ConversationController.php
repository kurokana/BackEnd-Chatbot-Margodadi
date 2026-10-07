<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\HitlEventType;
use App\Enums\PriorityLevel;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\HitlEvent;
use App\Models\Message;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Display a listing of conversations with multi-criteria filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Conversation::with(['user', 'category', 'assignedOperator', 'messages' => function ($q) {
            $q->latest('created_at')->limit(1);
        }]);

        // Filter: Status / HITL
        $status = strtoupper((string) $request->query('status', ''));
        if ($status === 'NEED_HUMAN' || $status === 'HITL') {
            $query->where('needs_human', true);
        } elseif (! empty($status) && $status !== 'ALL') {
            if ($status === 'ASSIGNED') {
                $query->where('status', ConversationStatus::ASSIGNED->value);
            } elseif ($status === 'OPEN') {
                $query->where('status', ConversationStatus::OPEN->value);
            } elseif ($status === 'PENDING') {
                $query->where('status', ConversationStatus::PENDING->value);
            } elseif ($status === 'RESOLVED') {
                $query->where('status', ConversationStatus::RESOLVED->value);
            }
        }

        // Filter: Needs Human Flag
        if ($request->has('needs_human')) {
            $query->where('needs_human', filter_var($request->query('needs_human'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter: Channel
        if ($channel = $request->query('channel')) {
            $query->where('channel', strtoupper($channel));
        }

        // Filter: Priority
        if ($priority = $request->query('priority')) {
            $query->where('priority', strtoupper($priority));
        }

        // Filter: Operator
        if ($operatorId = $request->query('operator_id')) {
            $query->where('assigned_operator_id', $operatorId);
        }

        // Filter: Category
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Search: Citizen Name, External ID, Phone, or Message Content
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(citizen_name) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(external_conversation_id) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('phone_number', 'LIKE', '%'.$search.'%')
                            ->orWhere('anonymous_code', 'LIKE', '%'.$search.'%');
                    })
                    ->orWhereHas('messages', function ($mq) use ($search) {
                        $mq->whereRaw('LOWER(content) LIKE ?', ['%'.strtolower($search).'%']);
                    });
            });
        }

        // Sorting
        $query->orderBy('needs_human', 'desc')
            ->orderBy('updated_at', 'desc');

        $perPage = (int) $request->query('per_page', 15);
        $conversations = $query->paginate($perPage);

        // Format items for frontend
        $formattedItems = collect($conversations->items())->map(function (Conversation $conv) {
            $lastMsg = $conv->messages->first();

            return [
                'id' => $conv->external_conversation_id ?? ('CV-'.str_pad($conv->conversation_id, 5, '0', STR_PAD_LEFT)),
                'conversation_id' => $conv->conversation_id,
                'external_id' => $conv->external_conversation_id,
                'citizen_name' => $conv->citizen_name ?? ($conv->user?->anonymous_code ?? 'Warga Anonim'),
                'channel' => strtolower($conv->channel?->value ?? 'web'),
                'category' => $conv->category?->name ?? 'Informasi Publik',
                'category_id' => $conv->category_id,
                'status' => $conv->status?->value ?? 'OPEN',
                'needs_human' => (bool) $conv->needs_human,
                'priority' => $conv->priority?->value ?? 'MEDIUM',
                'assigned_operator' => $conv->assignedOperator ? [
                    'id' => 'OP-'.str_pad($conv->assignedOperator->operator_id, 2, '0', STR_PAD_LEFT),
                    'operator_id' => $conv->assignedOperator->operator_id,
                    'name' => $conv->assignedOperator->name,
                    'email' => $conv->assignedOperator->email,
                    'avatar' => $conv->assignedOperator->avatar_url ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                ] : null,
                'last_message' => $lastMsg?->content ?? 'Percakapan baru dimulai',
                'last_message_sender' => $lastMsg?->sender_type?->value ?? 'USER',
                'updated_at' => $conv->updated_at?->toIso8601String(),
                'created_at' => $conv->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedItems,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Display a specific conversation detail with its complete message thread and HITL timeline.
     */
    public function show(string|int $id): JsonResponse
    {
        $conv = Conversation::with([
            'user',
            'category',
            'assignedOperator',
            'messages.operator',
            'hitlEvents.operator',
            'assignments.operator',
        ])
            ->where('conversation_id', is_numeric($id) ? (int) $id : null)
            ->orWhere('external_conversation_id', (string) $id)
            ->first();

        if (! $conv) {
            return response()->json([
                'status' => 'error',
                'message' => 'Percakapan tidak ditemukan.',
            ], 404);
        }

        $messages = $conv->messages->map(function (Message $m) {
            $sender = $m->sender_type?->value ?? 'USER';
            $senderLabel = 'Warga';
            if ($sender === 'BOT') {
                $sender = 'AI';
                $senderLabel = 'Virtual Guide AI';
            } elseif ($sender === 'OPERATOR') {
                $senderLabel = ($m->operator?->name ?? 'Operator').' (Aparatur)';
            } elseif ($sender === 'SYSTEM') {
                $sender = 'AI';
                $senderLabel = 'Sistem Virtual Guide';
            }

            return [
                'id' => 'm'.$m->message_id,
                'message_id' => $m->message_id,
                'sender' => $sender,
                'sender_name' => $senderLabel,
                'content' => $m->content,
                'is_system_alert' => $m->sender_type === SenderType::SYSTEM || ($m->metadata['is_alert'] ?? false),
                'timestamp' => $m->created_at?->format('H:i') ?? '',
                'date' => $m->created_at?->format('d M Y') ?? '',
                'created_at' => $m->created_at?->toIso8601String(),
            ];
        });

        $statusHistory = $conv->hitlEvents->map(function (HitlEvent $e) {
            return [
                'status' => $e->event_type?->value ?? 'STATUS_CHANGE',
                'timestamp' => $e->created_at?->format('d M Y, H:i') ?? '',
                'note' => $e->notes ?? 'Pembaruan status percakapan',
                'operator_name' => $e->operator?->name,
            ];
        });

        // If statusHistory is empty, build default starting history
        if ($statusHistory->isEmpty()) {
            $statusHistory = collect([
                [
                    'status' => 'OPEN',
                    'timestamp' => $conv->created_at?->format('d M Y, H:i') ?? '',
                    'note' => 'Percakapan dimulai via '.ucfirst($conv->channel?->value ?? 'Web'),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $conv->external_conversation_id ?? ('CV-'.str_pad($conv->conversation_id, 5, '0', STR_PAD_LEFT)),
                'conversation_id' => $conv->conversation_id,
                'external_id' => $conv->external_conversation_id,
                'session_id' => 'sess_'.$conv->channel?->value.'_'.$conv->conversation_id,
                'citizen_name' => $conv->citizen_name ?? ($conv->user?->anonymous_code ?? 'Warga Anonim'),
                'channel' => strtolower($conv->channel?->value ?? 'web'),
                'category' => $conv->category?->name ?? 'Informasi Publik',
                'category_id' => $conv->category_id,
                'status' => $conv->status?->value ?? 'OPEN',
                'needs_human' => (bool) $conv->needs_human,
                'priority' => $conv->priority?->value ?? 'MEDIUM',
                'phone' => $conv->user?->phone_number ?? '-',
                'anonymous_code' => $conv->user?->anonymous_code ?? '-',
                'assigned_operator' => $conv->assignedOperator ? [
                    'id' => 'OP-'.str_pad($conv->assignedOperator->operator_id, 2, '0', STR_PAD_LEFT),
                    'operator_id' => $conv->assignedOperator->operator_id,
                    'name' => $conv->assignedOperator->name,
                    'email' => $conv->assignedOperator->email,
                    'avatar' => $conv->assignedOperator->avatar_url ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                ] : null,
                'messages' => $messages,
                'status_history' => $statusHistory,
                'created_at' => $conv->created_at?->toIso8601String(),
                'updated_at' => $conv->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Send an operator reply to the conversation.
     */
    public function reply(Request $request, string|int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'close_conversation' => ['nullable', 'boolean'],
        ]);

        $conv = Conversation::where('conversation_id', is_numeric($id) ? (int) $id : null)
            ->orWhere('external_conversation_id', (string) $id)
            ->firstOrFail();

        $operator = $request->user();

        // 1. Create message
        $message = Message::create([
            'conversation_id' => $conv->conversation_id,
            'operator_id' => $operator->operator_id,
            'sender_type' => SenderType::OPERATOR,
            'content' => $validated['content'],
            'created_at' => now(),
        ]);

        // 2. Update conversation
        $conv->last_message_at = now();
        $conv->assigned_operator_id = $operator->operator_id;

        if ($request->boolean('close_conversation')) {
            $conv->status = ConversationStatus::RESOLVED;
            $conv->needs_human = false;
            $conv->resolved_at = now();

            HitlEvent::create([
                'conversation_id' => $conv->conversation_id,
                'operator_id' => $operator->operator_id,
                'event_type' => HitlEventType::RESOLVED,
                'notes' => 'Percakapan diselesaikan oleh operator '.$operator->name,
                'created_at' => now(),
            ]);
        } else {
            $conv->status = ConversationStatus::ASSIGNED;

            HitlEvent::create([
                'conversation_id' => $conv->conversation_id,
                'operator_id' => $operator->operator_id,
                'event_type' => HitlEventType::OPERATOR_RESPONSE,
                'notes' => 'Operator '.$operator->name.' mengirimkan balasan pesan',
                'created_at' => now(),
            ]);
        }

        $conv->save();

        // 3. Log Activity
        ActivityLog::create([
            'operator_id' => $operator->operator_id,
            'actor_name' => $operator->name,
            'action' => 'OPERATOR_RESPONSE',
            'target' => '#'.($conv->external_conversation_id ?? ('CV-'.$conv->conversation_id)),
            'description' => 'Operator membalas percakapan warga: "'.\Illuminate\Support\Str::limit($validated['content'], 60).'"',
            'channel' => $conv->channel ?? ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pesan balasan berhasil dikirim.',
            'data' => [
                'message' => [
                    'id' => 'm'.$message->message_id,
                    'message_id' => $message->message_id,
                    'sender' => 'OPERATOR',
                    'sender_name' => $operator->name.' (Operator)',
                    'content' => $message->content,
                    'timestamp' => $message->created_at->format('H:i'),
                    'date' => $message->created_at->format('d M Y'),
                    'created_at' => $message->created_at->toIso8601String(),
                ],
                'conversation_status' => $conv->status?->value,
                'needs_human' => (bool) $conv->needs_human,
            ],
        ]);
    }

    /**
     * Update conversation status.
     */
    public function updateStatus(Request $request, string|int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:OPEN,ASSIGNED,PENDING,RESOLVED'],
            'notes' => ['nullable', 'string'],
        ]);

        $conv = Conversation::where('conversation_id', is_numeric($id) ? (int) $id : null)
            ->orWhere('external_conversation_id', (string) $id)
            ->firstOrFail();

        $operator = $request->user();
        $newStatus = ConversationStatus::from($validated['status']);

        $conv->status = $newStatus;
        if ($newStatus === ConversationStatus::RESOLVED) {
            $conv->needs_human = false;
            $conv->resolved_at = now();
        }

        $conv->save();

        HitlEvent::create([
            'conversation_id' => $conv->conversation_id,
            'operator_id' => $operator->operator_id,
            'event_type' => $newStatus === ConversationStatus::RESOLVED ? HitlEventType::RESOLVED : HitlEventType::STATUS_CHANGE,
            'notes' => $validated['notes'] ?? ('Status percakapan diubah menjadi '.$newStatus->value),
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'operator_id' => $operator->operator_id,
            'actor_name' => $operator->name,
            'action' => 'STATUS_CHANGE',
            'target' => '#'.($conv->external_conversation_id ?? ('CV-'.$conv->conversation_id)),
            'description' => 'Operator mengubah status tiket menjadi '.$newStatus->value,
            'channel' => $conv->channel ?? ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status percakapan berhasil diperbarui.',
            'data' => [
                'status' => $conv->status?->value,
                'needs_human' => (bool) $conv->needs_human,
            ],
        ]);
    }

    /**
     * Assign conversation to a designated operator.
     */
    public function assign(Request $request, string|int $id): JsonResponse
    {
        $validated = $request->validate([
            'operator_id' => ['required', 'exists:operators,operator_id'],
            'notes' => ['nullable', 'string'],
        ]);

        $conv = Conversation::where('conversation_id', is_numeric($id) ? (int) $id : null)
            ->orWhere('external_conversation_id', (string) $id)
            ->firstOrFail();

        $targetOperator = Operator::findOrFail($validated['operator_id']);
        $currentActor = $request->user();

        $conv->assigned_operator_id = $targetOperator->operator_id;
        $conv->status = ConversationStatus::ASSIGNED;
        $conv->save();

        ConversationAssignment::create([
            'conversation_id' => $conv->conversation_id,
            'operator_id' => $targetOperator->operator_id,
            'assigned_at' => now(),
            'created_at' => now(),
        ]);

        HitlEvent::create([
            'conversation_id' => $conv->conversation_id,
            'operator_id' => $targetOperator->operator_id,
            'event_type' => HitlEventType::OPERATOR_ASSIGNED,
            'notes' => $validated['notes'] ?? ('Ditugaskan kepada '.$targetOperator->name.' oleh '.$currentActor->name),
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'operator_id' => $currentActor->operator_id,
            'actor_name' => $currentActor->name,
            'action' => 'ASSIGN_OPERATOR',
            'target' => '#'.($conv->external_conversation_id ?? ('CV-'.$conv->conversation_id)),
            'description' => 'Penugasan percakapan kepada '.$targetOperator->name,
            'channel' => $conv->channel ?? ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Percakapan berhasil ditugaskan kepada '.$targetOperator->name,
            'data' => [
                'assigned_operator' => [
                    'id' => 'OP-'.str_pad($targetOperator->operator_id, 2, '0', STR_PAD_LEFT),
                    'operator_id' => $targetOperator->operator_id,
                    'name' => $targetOperator->name,
                    'email' => $targetOperator->email,
                    'avatar' => $targetOperator->avatar_url,
                ],
                'status' => $conv->status?->value,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChannelType;
use App\Enums\OperatorStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatorController extends Controller
{
    /**
     * Display a listing of operators with their workload statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $operators = Operator::where('is_active', true)
            ->withCount([
                'assignedConversations as assigned_count' => function ($q) {
                    $q->whereIn('status', ['OPEN', 'ASSIGNED', 'PENDING']);
                },
                'assignedConversations as resolved_count' => function ($q) {
                    $q->where('status', 'RESOLVED');
                },
            ])
            ->orderBy('operator_id')
            ->get();

        $formatted = $operators->map(function (Operator $op) {
            return [
                'id' => 'OP-'.str_pad($op->operator_id, 2, '0', STR_PAD_LEFT),
                'operator_id' => $op->operator_id,
                'name' => $op->name,
                'email' => $op->email,
                'role' => $op->role?->value ?? 'OPERATOR',
                'status' => $op->status?->value ?? 'OFFLINE',
                'avatar' => $op->avatar_url ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                'assignedCount' => $op->assigned_count ?? 0,
                'resolvedCount' => $op->resolved_count ?? 0,
                'avgResponseTime' => '2.4 mnt',
                'phone' => $op->phone ?? '-',
                'created_at' => $op->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * Update operator presence status (ONLINE, OFFLINE, BUSY).
     */
    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:ONLINE,OFFLINE,BUSY'],
        ]);

        $operator = Operator::where('operator_id', is_numeric($id) ? (int) $id : null)
            ->orWhere('email', (string) $id)
            ->firstOrFail();

        $oldStatus = $operator->status?->value;
        $operator->status = OperatorStatus::from($validated['status']);
        $operator->save();

        ActivityLog::create([
            'operator_id' => $operator->operator_id,
            'actor_name' => $operator->name,
            'action' => 'OPERATOR_STATUS',
            'target' => '#OP-'.str_pad($operator->operator_id, 2, '0', STR_PAD_LEFT),
            'description' => 'Operator mengubah status dari '.$oldStatus.' ke '.$operator->status->value,
            'channel' => ChannelType::WEB,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status operator berhasil diperbarui.',
            'data' => [
                'operator_id' => $operator->operator_id,
                'status' => $operator->status?->value,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of system activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('operator')->latest('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($operatorId = $request->query('operator_id')) {
            $query->where('operator_id', $operatorId);
        }

        $perPage = (int) $request->query('per_page', 20);
        $logs = $query->paginate($perPage);

        $formatted = collect($logs->items())->map(function (ActivityLog $log) {
            return [
                'id' => 'LOG-'.str_pad($log->log_id, 4, '0', STR_PAD_LEFT),
                'log_id' => $log->log_id,
                'action' => $log->action,
                'actor' => $log->actor_name ?? ($log->operator?->name ?? 'Sistem'),
                'target' => $log->target ?? '-',
                'description' => $log->description,
                'channel' => strtolower($log->channel?->value ?? 'web'),
                'timestamp' => $log->created_at?->format('H:i') ?? '',
                'date' => $log->created_at?->format('d M Y') ?? '',
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}

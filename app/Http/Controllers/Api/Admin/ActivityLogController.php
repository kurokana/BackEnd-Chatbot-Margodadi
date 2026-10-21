<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of system activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('operator:operator_id,name')->latest('created_at');

        // Action Filter
        if ($action = $request->query('action')) {
            if (strtoupper($action) !== 'ALL') {
                if (str_contains($action, ',')) {
                    $actions = array_filter(explode(',', $action));
                    $query->whereIn('action', $actions);
                } else {
                    $query->where('action', $action);
                }
            }
        }

        // Operator ID Filter
        if ($operatorId = $request->query('operator_id')) {
            $query->where('operator_id', $operatorId);
        }

        // Channel Filter
        if ($channel = $request->query('channel')) {
            if (strtoupper($channel) !== 'ALL') {
                $query->where('channel', strtoupper($channel));
            }
        }

        // Keyword Search (actor_name, description, target, action)
        if ($search = $request->query('search')) {
            $searchTerm = '%' . strtolower(trim($search)) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(actor_name) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(target) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(action) LIKE ?', [$searchTerm])
                  ->orWhereHas('operator', function ($opQuery) use ($searchTerm) {
                      $opQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $logs = $query->paginate($perPage);

        $formatted = collect($logs->items())->map(function (ActivityLog $log) {
            return [
                'id' => 'LOG-' . str_pad($log->log_id, 4, '0', STR_PAD_LEFT),
                'log_id' => $log->log_id,
                'action' => $log->action,
                'actor' => $log->actor_name ?? ($log->operator?->name ?? 'Sistem'),
                'target' => $log->target ?? '-',
                'description' => $log->description,
                'channel' => strtolower($log->channel?->value ?? ($log->channel ?? 'web')),
                'timestamp' => $log->created_at ? Carbon::parse($log->created_at)->timezone('Asia/Jakarta')->format('H:i') : '',
                'date' => $log->created_at ? Carbon::parse($log->created_at)->timezone('Asia/Jakarta')->translatedFormat('d M Y') : '',
                'created_at' => $log->created_at ? Carbon::parse($log->created_at)->timezone('Asia/Jakarta')->toIso8601String() : null,
            ];
        });

        // Summary Statistics for Real-time Dashboard Cards
        $todayStart = Carbon::today();
        $totalCount = ActivityLog::count();
        $todayCount = ActivityLog::where('created_at', '>=', $todayStart)->count();
        $operatorActionsCount = ActivityLog::whereNotNull('operator_id')->count();
        $hitlEscalationsCount = ActivityLog::whereIn('action', ['HITL_ESCALATION', 'TAKE_OVER', 'ASSIGN_OPERATOR'])->count();

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'stats' => [
                'total_logs' => $totalCount,
                'today_logs' => $todayCount,
                'operator_actions' => $operatorActionsCount,
                'hitl_escalations' => $hitlEscalationsCount,
            ],
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}


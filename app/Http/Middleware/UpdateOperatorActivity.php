<?php

namespace App\Http\Middleware;

use App\Enums\ChannelType;
use App\Enums\OperatorStatus;
use App\Models\ActivityLog;
use App\Models\Operator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateOperatorActivity
{
    /**
     * Threshold in minutes before an idle operator session is automatically expired.
     */
    public const IDLE_TIMEOUT_MINUTES = 60;

    /**
     * Handle an incoming request.
     * Enforce sliding-window idle timeout (60 minutes) and keep presence active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Operator && $user->is_active) {
            $lastActivity = $user->last_activity_at;
            $now = now();

            // 1. Enforce 60-minute idle session timeout
            if ($lastActivity && $lastActivity->lessThanOrEqualTo($now) && $lastActivity->diffInMinutes($now) >= self::IDLE_TIMEOUT_MINUTES) {
                // Revoke current Sanctum token
                $request->user()->currentAccessToken()?->delete();

                // Set status OFFLINE
                $user->forceFill([
                    'status' => OperatorStatus::OFFLINE,
                ])->saveQuietly();

                // Audit log session timeout
                ActivityLog::create([
                    'operator_id' => $user->operator_id,
                    'actor_name' => $user->name,
                    'action' => 'AUTO_LOGOUT',
                    'target' => '#OP-'.str_pad($user->operator_id, 2, '0', STR_PAD_LEFT),
                    'description' => "Sesi akun aparatur {$user->name} berakhir otomatis karena inaktivitas > 1 jam.",
                    'channel' => ChannelType::WEB,
                    'created_at' => now(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'code' => 'IDLE_TIMEOUT',
                    'message' => 'Sesi Anda telah kedaluwarsa karena tidak ada aktivitas selama lebih dari 1 jam.',
                ], 401);
            }

            // 2. Sliding renewal: refresh last_activity_at and presence
            $currentStatus = $user->status;
            $newStatus = ($currentStatus === OperatorStatus::BUSY) 
                ? OperatorStatus::BUSY 
                : OperatorStatus::ONLINE;

            $user->forceFill([
                'last_activity_at' => now(),
                'status' => $newStatus,
            ])->saveQuietly();
        }

        return $next($request);
    }
}

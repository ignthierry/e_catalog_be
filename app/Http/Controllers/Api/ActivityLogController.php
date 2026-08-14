<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs with filters and summary stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query();

        // 1. Search Query (keyword)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('device', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // 2. Filter by Action
        if ($action = $request->input('action')) {
            if ($action !== 'ALL') {
                $query->where('action', $action);
            }
        }

        // 3. Filter by Role
        if ($role = $request->input('role')) {
            if ($role !== 'ALL') {
                $query->where('user_role', $role);
            }
        }

        // 4. Filter by Date range
        if ($dateFilter = $request->input('date')) {
            if ($dateFilter === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($dateFilter === 'yesterday') {
                $query->whereDate('created_at', Carbon::yesterday());
            } elseif ($dateFilter === '7days') {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            } elseif ($dateFilter === '30days') {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            }
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // 5. Pagination
        $perPage = min((int) $request->input('per_page', 20), 100);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // 6. Aggregate Statistics
        $totalLogs = ActivityLog::count();
        $todayCount = ActivityLog::whereDate('created_at', Carbon::today())->count();
        $loginCount = ActivityLog::where('action', 'LOGIN')->whereDate('created_at', Carbon::today())->count();
        $uniqueIpsCount = ActivityLog::distinct('ip_address')->count('ip_address');

        // Recent distinct action types for filter options
        $availableActions = ActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'pagination' => [
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage(),
                'perPage' => $logs->perPage(),
                'total' => $logs->total(),
                'hasMorePages' => $logs->hasMorePages(),
            ],
            'stats' => [
                'total' => $totalLogs,
                'today' => $todayCount,
                'todayLogins' => $loginCount,
                'uniqueIps' => $uniqueIpsCount,
            ],
            'availableActions' => $availableActions,
        ]);
    }

    /**
     * Clear activity logs (Maintenance / Clean-up).
     */
    public function clear(Request $request): JsonResponse
    {
        $days = (int) $request->input('older_than_days', 30);

        if ($days > 0) {
            $deleted = ActivityLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();
            $message = "Berhasil membersihkan {$deleted} log aktivitas yang lebih lama dari {$days} hari.";
        } else {
            // Clear all
            ActivityLog::truncate();
            $message = "Semua data log aktivitas berhasil dikosongkan.";
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
        ]);
    }
}

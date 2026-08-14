<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    /**
     * Record an activity log entry.
     *
     * @param Request|null $request
     * @param string $action (e.g. LOGIN, LOGOUT, CREATE_PRODUCT, UPDATE_ORDER, etc.)
     * @param string $description
     * @param User|array|null $user
     * @param array|null $properties
     * @return ActivityLog|null
     */
    public static function log(
        ?Request $request,
        string $action,
        string $description,
        $user = null,
        ?array $properties = null
    ): ?ActivityLog {
        try {
            $req = $request ?: request();

            // 1. Resolve User details
            $userId = null;
            $userName = null;
            $userEmail = null;
            $userRole = null;

            if ($user instanceof User) {
                $userId = $user->id;
                $userName = $user->name;
                $userEmail = $user->email;
                $userRole = $user->role;
            } elseif (is_array($user)) {
                $userId = $user['id'] ?? null;
                $userName = $user['name'] ?? null;
                $userEmail = $user['email'] ?? null;
                $userRole = $user['role'] ?? null;
            } elseif ($req && $req->user()) {
                $authUser = $req->user();
                $userId = $authUser->id;
                $userName = $authUser->name;
                $userEmail = $authUser->email;
                $userRole = $authUser->role;
            }

            // 2. Extract Client IP
            $ipAddress = self::resolveClientIp($req);

            // 3. Extract & Parse User-Agent
            $userAgent = $req ? $req->userAgent() : null;
            $device = self::parseDevice($userAgent);

            return ActivityLog::create([
                'user_id' => $userId,
                'user_name' => $userName ?: 'Tamu / Sistem',
                'user_email' => $userEmail ?: '-',
                'user_role' => $userRole ?: 'guest',
                'action' => strtoupper($action),
                'description' => $description,
                'ip_address' => $ipAddress ?: '127.0.0.1',
                'user_agent' => $userAgent,
                'device' => $device,
                'properties' => $properties ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to record activity log [{$action}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve true client IP address (supporting Cloudflare & proxies).
     */
    public static function resolveClientIp(?Request $request): string
    {
        if (!$request) {
            return '127.0.0.1';
        }

        if ($request->headers->has('cf-connecting-ip')) {
            return $request->headers->get('cf-connecting-ip');
        }

        if ($request->headers->has('x-real-ip')) {
            return $request->headers->get('x-real-ip');
        }

        if ($request->headers->has('x-forwarded-for')) {
            $ips = explode(',', $request->headers->get('x-forwarded-for'));
            return trim($ips[0]);
        }

        return $request->ip() ?: '127.0.0.1';
    }

    /**
     * Parse User-Agent string to human-readable device, browser, and OS.
     */
    public static function parseDevice(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Perangkat Tidak Diketahui';
        }

        $os = 'Unknown OS';
        if (preg_match('/windows nt 10\.0/i', $userAgent)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.3/i', $userAgent)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/windows nt 6\.1/i', $userAgent)) {
            $os = 'Windows 7';
        } elseif (preg_match('/windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/android ([0-9.]+)?/i', $userAgent, $m)) {
            $os = 'Android' . (isset($m[1]) ? ' ' . $m[1] : '');
        } elseif (preg_match('/iphone os ([0-9_]+)?/i', $userAgent, $m)) {
            $os = 'iPhone iOS' . (isset($m[1]) ? ' ' . str_replace('_', '.', $m[1]) : '');
        } elseif (preg_match('/ipad.+os ([0-9_]+)?/i', $userAgent, $m)) {
            $os = 'iPadOS' . (isset($m[1]) ? ' ' . str_replace('_', '.', $m[1]) : '');
        } elseif (preg_match('/macintosh|mac os x ([0-9_]+)?/i', $userAgent, $m)) {
            $os = 'macOS' . (isset($m[1]) ? ' ' . str_replace('_', '.', $m[1]) : '');
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        }

        $browser = 'Browser';
        if (preg_match('/edg\/([0-9.]+)/i', $userAgent, $m)) {
            $browser = 'Edge ' . explode('.', $m[1])[0];
        } elseif (preg_match('/chrome\/([0-9.]+)/i', $userAgent, $m)) {
            $browser = 'Chrome ' . explode('.', $m[1])[0];
        } elseif (preg_match('/firefox\/([0-9.]+)/i', $userAgent, $m)) {
            $browser = 'Firefox ' . explode('.', $m[1])[0];
        } elseif (preg_match('/safari\/([0-9.]+)/i', $userAgent, $m) && !preg_match('/chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/opera|opr\/([0-9.]+)/i', $userAgent, $m)) {
            $browser = 'Opera';
        }

        return "{$browser} on {$os}";
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    /**
     * Cache duration constants
     */
    const CACHE_SHORT = 300; // 5 minutes
    const CACHE_MEDIUM = 1800; // 30 minutes
    const CACHE_LONG = 3600; // 1 hour
    const CACHE_DAILY = 86400; // 24 hours

    /**
     * Cache attendance statistics
     */
    public static function getAttendanceStats(int $tenantId, string $date): array
    {
        $cacheKey = "attendance_stats_{$tenantId}_{$date}";
        
        return Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($tenantId, $date) {
            return \App\Models\Attendance::where('tenant_id', $tenantId)
                ->whereDate('check_in_at', $date)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                    AVG(late_minutes) as avg_late_minutes
                ')
                ->first()
                ->toArray();
        });
    }

    /**
     * Cache user attendance summary
     */
    public static function getUserAttendanceSummary(int $userId, string $month): array
    {
        $cacheKey = "user_attendance_summary_{$userId}_{$month}";
        
        return Cache::remember($cacheKey, self::CACHE_LONG, function () use ($userId, $month) {
            $attendances = \App\Models\Attendance::where('user_id', $userId)
                ->where('check_in_at', 'like', $month . '%')
                ->get();

            return [
                'total_days' => $attendances->count(),
                'present_days' => $attendances->where('status', 'present')->count(),
                'late_days' => $attendances->where('status', 'late')->count(),
                'absent_days' => $attendances->where('status', 'absent')->count(),
                'total_late_minutes' => $attendances->sum('late_minutes'),
                'avg_work_hours' => $attendances->avg(function ($attendance) {
                    if ($attendance->actual_work_hours) {
                        $time = \Carbon\Carbon::createFromFormat('H:i:s', $attendance->actual_work_hours);
                        return $time->hour + ($time->minute / 60);
                    }
                    return 0;
                }),
            ];
        });
    }

    /**
     * Cache leave balance summary
     */
    public static function getLeaveBalanceSummary(int $userId, string $policyYear): array
    {
        $cacheKey = "leave_balance_summary_{$userId}_{$policyYear}";
        
        return Cache::remember($cacheKey, self::CACHE_LONG, function () use ($userId, $policyYear) {
            return \App\Services\LeaveBalanceService::getBalanceSummary(
                \App\Models\User::find($userId),
                $policyYear
            );
        });
    }

    /**
     * Cache tenant settings
     */
    public static function getTenantSettings(int $tenantId): array
    {
        $cacheKey = "tenant_settings_{$tenantId}";
        
        return Cache::remember($cacheKey, self::CACHE_DAILY, function () use ($tenantId) {
            $tenant = \App\Models\Tenant::with(['securitySettings'])->find($tenantId);
            
            return [
                'working_hours' => [
                    'start' => $tenant->work_start_time,
                    'end' => $tenant->work_end_time,
                    'tolerance' => $tenant->late_tolerance_minutes,
                    'working_days' => $tenant->working_days,
                ],
                'geofencing' => [
                    'enabled' => $tenant->enforce_geofencing,
                    'latitude' => $tenant->office_latitude,
                    'longitude' => $tenant->office_longitude,
                    'radius' => $tenant->geofence_radius_meters,
                ],
                'security' => [
                    'require_2fa' => $tenant->securitySettings?->require_2fa ?? false,
                    'max_login_attempts' => $tenant->securitySettings?->max_login_attempts ?? 5,
                    'session_timeout' => $tenant->securitySettings?->session_timeout_minutes ?? 120,
                ],
            ];
        });
    }

    /**
     * Cache API responses
     */
    public static function cacheApiResponse(string $key, callable $callback, int $duration = self::CACHE_SHORT): mixed
    {
        return Cache::remember("api_{$key}", $duration, $callback);
    }

    /**
     * Invalidate cache by pattern
     */
    public static function invalidatePattern(string $pattern): void
    {
        if (config('cache.default') === 'redis') {
            $keys = Redis::keys("*{$pattern}*");
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } else {
            // For other cache drivers, we'll need to track keys manually
            // This is a simplified approach
            Cache::flush();
        }
    }

    /**
     * Invalidate attendance related caches
     */
    public static function invalidateAttendanceCache(int $tenantId, int $userId, string $date): void
    {
        $patterns = [
            "attendance_stats_{$tenantId}_{$date}",
            "user_attendance_summary_{$userId}_" . substr($date, 0, 7),
            "api_attendance_summary_{$userId}",
            "api_attendance_today_{$userId}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Invalidate leave related caches
     */
    public static function invalidateLeaveCache(int $userId, string $year): void
    {
        $patterns = [
            "leave_balance_summary_{$userId}_{$year}",
            "api_leave_summary_{$userId}_{$year}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Warm up cache for common queries
     */
    public static function warmUpCache(): void
    {
        $tenants = \App\Models\Tenant::all();
        $today = now()->toDateString();
        $currentMonth = now()->format('Y-m');

        foreach ($tenants as $tenant) {
            // Warm up attendance stats
            self::getAttendanceStats($tenant->id, $today);
            
            // Warm up tenant settings
            self::getTenantSettings($tenant->id);
            
            // Warm up user summaries for active users
            $users = \App\Models\User::where('tenant_id', $tenant->id)
                ->whereHas('roles', function($q) {
                    $q->where('name', 'employee');
                })
                ->limit(10) // Limit to avoid overwhelming
                ->get();

            foreach ($users as $user) {
                self::getUserAttendanceSummary($user->id, $currentMonth);
                self::getLeaveBalanceSummary($user->id, (string)now()->year);
            }
        }
    }
}
<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\User;
use App\Notifications\LateArrivalAlert;
use App\Notifications\GeofenceViolationAlert;
use App\Services\CacheService;
use App\Jobs\ProcessAttendanceJob;

class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        // Invalidate related caches
        CacheService::invalidateAttendanceCache(
            $attendance->tenant_id,
            $attendance->user_id,
            $attendance->check_in_at->toDateString()
        );

        // Send late arrival alert
        if ($attendance->is_late && $attendance->late_minutes > 0) {
            $this->sendLateArrivalAlert($attendance);
        }

        // Send geofence violation alert
        if (!$attendance->is_within_geofence && $attendance->user->tenant->enforce_geofencing) {
            $this->sendGeofenceViolationAlert($attendance);
        }

        // Queue background processing
        ProcessAttendanceJob::dispatch($attendance);
    }

    public function updated(Attendance $attendance): void
    {
        // Invalidate related caches when attendance is updated
        CacheService::invalidateAttendanceCache(
            $attendance->tenant_id,
            $attendance->user_id,
            $attendance->check_in_at->toDateString()
        );
    }

    private function sendLateArrivalAlert(Attendance $attendance): void
    {
        // Get admins and supervisors to notify
        $admins = User::where('tenant_id', $attendance->tenant_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new LateArrivalAlert($attendance));
        }
    }

    private function sendGeofenceViolationAlert(Attendance $attendance): void
    {
        // Get admins to notify about geofence violations
        $admins = User::where('tenant_id', $attendance->tenant_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new GeofenceViolationAlert($attendance));
        }
    }
}
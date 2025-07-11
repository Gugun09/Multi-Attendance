<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Process check-in with validation
     */
    public static function processCheckIn(
        User $user, 
        ?float $latitude = null, 
        ?float $longitude = null, 
        ?string $location = null,
        ?string $notes = null
    ): array {
        $tenant = $user->tenant;
        $shift = $user->shift;
        $now = now();

        // Check if already checked in today
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_at', $now->toDateString())
            ->first();

        if ($existingAttendance) {
            return [
                'success' => false,
                'message' => 'You have already checked in today',
                'attendance' => $existingAttendance
            ];
        }

        // Validate geofencing if coordinates provided
        $geofenceResult = ['is_valid' => true, 'is_within_geofence' => true, 'distance' => 0];
        if ($latitude && $longitude && $tenant) {
            $geofenceResult = GeofencingService::validateAttendanceLocation($tenant, $latitude, $longitude);
            
            if (!$geofenceResult['is_valid']) {
                return [
                    'success' => false,
                    'message' => $geofenceResult['message'],
                    'geofence_error' => true
                ];
            }
        }

        // Determine if late
        $isLate = false;
        $lateMinutes = 0;
        $status = 'present';

        if ($shift) {
            $isLate = $shift->isLate($now);
            $lateMinutes = $shift->calculateLateMinutes($now);
            $status = $isLate ? 'late' : 'present';
        } elseif ($tenant) {
            // Use tenant default working hours
            $workStart = Carbon::createFromFormat('H:i:s', $tenant->work_start_time);
            $tolerance = $workStart->addMinutes($tenant->late_tolerance_minutes);
            $isLate = $now->format('H:i:s') > $tolerance->format('H:i:s');
            $lateMinutes = $isLate ? $now->diffInMinutes($workStart) : 0;
            $status = $isLate ? 'late' : 'present';
        }

        // Create attendance record
        $attendance = Attendance::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'check_in_at' => $now,
            'check_in_location' => $location,
            'check_in_latitude' => $latitude,
            'check_in_longitude' => $longitude,
            'is_within_geofence' => $geofenceResult['is_within_geofence'],
            'distance_from_office' => $geofenceResult['distance'],
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
            'status' => $status,
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => $isLate 
                ? "Checked in successfully (Late by {$lateMinutes} minutes)" 
                : 'Checked in successfully',
            'attendance' => $attendance,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes
        ];
    }

    /**
     * Process check-out with validation
     */
    public static function processCheckOut(
        User $user,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $location = null,
        ?string $notes = null
    ): array {
        $now = now();

        // Find today's attendance
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_at', $now->toDateString())
            ->whereNull('check_out_at')
            ->first();

        if (!$attendance) {
            return [
                'success' => false,
                'message' => 'No check-in record found for today or already checked out'
            ];
        }

        // Validate geofencing if coordinates provided
        $geofenceResult = ['is_valid' => true, 'is_within_geofence' => true, 'distance' => 0];
        if ($latitude && $longitude && $user->tenant) {
            $geofenceResult = GeofencingService::validateAttendanceLocation($user->tenant, $latitude, $longitude);
            
            // For check-out, we might be more lenient or just log the distance
            // You can adjust this based on business requirements
        }

        // Calculate actual work hours
        $checkInTime = Carbon::parse($attendance->check_in_at);
        $workHours = $checkInTime->diffInMinutes($now);
        $actualWorkHours = gmdate('H:i:s', $workHours * 60);

        // Update attendance record
        $attendance->update([
            'check_out_at' => $now,
            'check_out_location' => $location,
            'check_out_latitude' => $latitude,
            'check_out_longitude' => $longitude,
            'actual_work_hours' => $actualWorkHours,
            'notes' => $attendance->notes . ($notes ? "\nCheck-out: " . $notes : ''),
        ]);

        return [
            'success' => true,
            'message' => 'Checked out successfully',
            'attendance' => $attendance->fresh(),
            'work_duration' => $actualWorkHours
        ];
    }

    /**
     * Get today's attendance for user
     */
    public static function getTodayAttendance(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->first();
    }

    /**
     * Check if user can check in/out
     */
    public static function canCheckIn(User $user): array
    {
        $tenant = $user->tenant;
        $shift = $user->shift;
        $now = now();

        // Check if today is a working day
        $workingDays = $shift ? $shift->working_days : ($tenant ? $tenant->working_days : []);
        $today = strtolower($now->format('l'));

        if (!in_array($today, $workingDays)) {
            return [
                'can_check_in' => false,
                'message' => 'Today is not a working day'
            ];
        }

        // Check if already checked in
        $existingAttendance = self::getTodayAttendance($user);
        if ($existingAttendance) {
            return [
                'can_check_in' => false,
                'can_check_out' => is_null($existingAttendance->check_out_at),
                'message' => is_null($existingAttendance->check_out_at) 
                    ? 'You can check out' 
                    : 'You have already completed attendance for today',
                'attendance' => $existingAttendance
            ];
        }

        return [
            'can_check_in' => true,
            'can_check_out' => false,
            'message' => 'You can check in'
        ];
    }
}
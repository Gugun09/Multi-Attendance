<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\CacheService;
use App\Jobs\ProcessAttendanceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);
        $month = $request->get('month', now()->format('Y-m'));

        $query = Attendance::where('user_id', $user->id)
            ->with(['user', 'tenant'])
            ->orderBy('check_in_at', 'desc');

        if ($month) {
            $query->where('check_in_at', 'like', $month . '%');
        }

        $attendances = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'attendances' => $attendances->items(),
                'pagination' => [
                    'current_page' => $attendances->currentPage(),
                    'last_page' => $attendances->lastPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total(),
                ]
            ]
        ]);
    }

    public function todayAttendance(Request $request)
    {
        $user = $request->user();
        
        $cacheKey = "api_attendance_today_{$user->id}";
        $attendance = CacheService::cacheApiResponse($cacheKey, function() use ($user) {
            return AttendanceService::getTodayAttendance($user);
        }, CacheService::CACHE_SHORT);

        return response()->json([
            'success' => true,
            'data' => [
                'attendance' => $attendance,
                'can_check_in' => AttendanceService::canCheckIn($user)
            ]
        ]);
    }

    public function checkIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        $result = AttendanceService::processCheckIn(
            $user,
            $request->latitude,
            $request->longitude,
            $request->location,
            $request->notes
        );

        // Queue background processing
        if ($result['success'] && $result['attendance']) {
            ProcessAttendanceJob::dispatch($result['attendance']);
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'attendance' => $result['attendance'] ?? null,
                'is_late' => $result['is_late'] ?? false,
                'late_minutes' => $result['late_minutes'] ?? 0,
            ]
        ], $result['success'] ? 200 : 400);
    }

    public function checkOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        $result = AttendanceService::processCheckOut(
            $user,
            $request->latitude,
            $request->longitude,
            $request->location,
            $request->notes
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'attendance' => $result['attendance'] ?? null,
                'work_duration' => $result['work_duration'] ?? null,
            ]
        ], $result['success'] ? 200 : 400);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $month = $request->get('month', now()->format('Y-m'));

        $cacheKey = "api_attendance_summary_{$user->id}_{$month}";
        $summary = CacheService::cacheApiResponse($cacheKey, function() use ($user, $month) {
            return CacheService::getUserAttendanceSummary($user->id, $month);
        }, CacheService::CACHE_MEDIUM);

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'total_days' => $summary['total_days'],
                'present_days' => $summary['present_days'],
                'late_days' => $summary['late_days'],
                'absent_days' => $summary['absent_days'],
                'attendance_rate' => $summary['total_days'] > 0 ? round((($summary['present_days'] + $summary['late_days']) / $summary['total_days']) * 100, 1) : 0,
                'total_late_minutes' => $summary['total_late_minutes'],
                'avg_work_hours' => round($summary['avg_work_hours'], 2),
            ]
        ]);
    }
}
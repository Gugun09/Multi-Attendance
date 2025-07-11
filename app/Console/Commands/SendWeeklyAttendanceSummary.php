<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Attendance;
use App\Notifications\AttendanceSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendWeeklyAttendanceSummary extends Command
{
    protected $signature = 'attendance:send-weekly-summary {--tenant-id=}';
    protected $description = 'Send weekly attendance summary to admins';

    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        // Get all tenants or specific tenant
        $tenantQuery = DB::table('tenants');
        if ($tenantId) {
            $tenantQuery->where('id', $tenantId);
        }
        $tenants = $tenantQuery->get();

        foreach ($tenants as $tenant) {
            $this->processTenantWeeklySummary($tenant, $startOfWeek, $endOfWeek);
        }

        $this->info('Weekly attendance summary sent successfully!');
    }

    private function processTenantWeeklySummary($tenant, $startOfWeek, $endOfWeek)
    {
        // Get attendance data for this week
        $attendanceData = Attendance::where('tenant_id', $tenant->id)
            ->whereBetween(DB::raw('DATE(check_in_at)'), [$startOfWeek, $endOfWeek])
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('AVG(CASE WHEN is_late = 1 THEN late_minutes ELSE 0 END) as avg_late_minutes')
            ])
            ->first();

        // Get total employees for this tenant
        $totalEmployees = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function($query) {
                $query->where('name', 'employee');
            })
            ->count();

        // Get most frequently late employee this week
        $topLateEmployee = Attendance::where('tenant_id', $tenant->id)
            ->whereBetween(DB::raw('DATE(check_in_at)'), [$startOfWeek, $endOfWeek])
            ->where('is_late', true)
            ->select([
                'user_id',
                DB::raw('COUNT(*) as late_count'),
                DB::raw('SUM(late_minutes) as total_late_minutes')
            ])
            ->groupBy('user_id')
            ->orderByDesc('late_count')
            ->with('user')
            ->first();

        $present = $attendanceData->present ?? 0;
        $late = $attendanceData->late ?? 0;
        $absent = $attendanceData->absent ?? 0;
        $total = $present + $late + $absent;

        $summaryData = [
            'tenant_name' => $tenant->name,
            'week_start' => $startOfWeek,
            'week_end' => $endOfWeek,
            'total_employees' => $totalEmployees,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'present_percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            'late_percentage' => $total > 0 ? round(($late / $total) * 100, 1) : 0,
            'absent_percentage' => $total > 0 ? round(($absent / $total) * 100, 1) : 0,
            'top_late_employee' => $topLateEmployee?->user->name,
            'top_late_minutes' => $topLateEmployee?->total_late_minutes ?? 0,
            'avg_late_minutes' => round($attendanceData->avg_late_minutes ?? 0, 1),
        ];

        // Send to admins
        $admins = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new AttendanceSummary($summaryData, 'weekly'));
        }

        $this->info("Weekly summary sent for tenant: {$tenant->name}");
    }
}
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Attendance;
use App\Notifications\AttendanceSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDailyAttendanceSummary extends Command
{
    protected $signature = 'attendance:send-daily-summary {--tenant-id=}';
    protected $description = 'Send daily attendance summary to admins';

    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $today = now()->toDateString();

        // Get all tenants or specific tenant
        $tenantQuery = DB::table('tenants');
        if ($tenantId) {
            $tenantQuery->where('id', $tenantId);
        }
        $tenants = $tenantQuery->get();

        foreach ($tenants as $tenant) {
            $this->processTenantSummary($tenant, $today);
        }

        $this->info('Daily attendance summary sent successfully!');
    }

    private function processTenantSummary($tenant, $today)
    {
        // Get attendance data for today
        $attendanceData = Attendance::where('tenant_id', $tenant->id)
            ->whereDate('check_in_at', $today)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('MAX(CASE WHEN is_late = 1 THEN late_minutes ELSE 0 END) as max_late_minutes')
            ])
            ->first();

        // Get total employees for this tenant
        $totalEmployees = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function($query) {
                $query->where('name', 'employee');
            })
            ->count();

        // Get top late employee
        $topLateEmployee = Attendance::where('tenant_id', $tenant->id)
            ->whereDate('check_in_at', $today)
            ->where('is_late', true)
            ->orderByDesc('late_minutes')
            ->with('user')
            ->first();

        // Calculate percentages
        $present = $attendanceData->present ?? 0;
        $late = $attendanceData->late ?? 0;
        $absent = max(0, $totalEmployees - ($present + $late));

        $summaryData = [
            'tenant_name' => $tenant->name,
            'date' => $today,
            'total_employees' => $totalEmployees,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'present_percentage' => $totalEmployees > 0 ? round(($present / $totalEmployees) * 100, 1) : 0,
            'late_percentage' => $totalEmployees > 0 ? round(($late / $totalEmployees) * 100, 1) : 0,
            'absent_percentage' => $totalEmployees > 0 ? round(($absent / $totalEmployees) * 100, 1) : 0,
            'top_late_employee' => $topLateEmployee?->user->name,
            'top_late_minutes' => $topLateEmployee?->late_minutes ?? 0,
        ];

        // Send to admins
        $admins = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new AttendanceSummary($summaryData, 'daily'));
        }

        $this->info("Summary sent for tenant: {$tenant->name}");
    }
}
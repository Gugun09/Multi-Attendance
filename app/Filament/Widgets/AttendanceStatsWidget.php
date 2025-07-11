<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Leave;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AttendanceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');

        // Base query berdasarkan role
        $attendanceQuery = Attendance::query();
        $userQuery = User::query();
        $leaveQuery = Leave::query();

        if (!$user->hasRole('super_admin')) {
            $attendanceQuery->where('tenant_id', $user->tenant_id);
            $userQuery->where('tenant_id', $user->tenant_id);
            $leaveQuery->where('tenant_id', $user->tenant_id);

            if ($user->hasRole('employee')) {
                $attendanceQuery->where('user_id', $user->id);
                $userQuery->where('id', $user->id);
                $leaveQuery->where('user_id', $user->id);
            }
        }

        // Today's attendance
        $todayAttendance = (clone $attendanceQuery)
            ->whereDate('check_in_at', $today)
            ->count();

        // Today's late arrivals
        $todayLate = (clone $attendanceQuery)
            ->whereDate('check_in_at', $today)
            ->where('is_late', true)
            ->count();

        // This month's attendance
        $monthlyAttendance = (clone $attendanceQuery)
            ->where('check_in_at', 'like', $thisMonth . '%')
            ->count();

        // Pending leave requests
        $pendingLeaves = (clone $leaveQuery)
            ->where('status', 'pending')
            ->count();

        // Total active employees
        $totalEmployees = (clone $userQuery)
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['employee', 'admin']);
            })
            ->count();

        return [
            Stat::make('Today\'s Attendance', $todayAttendance)
                ->description('Employees checked in today')
                ->descriptionIcon('heroicon-m-finger-print')
                ->color('success'),

            Stat::make('Late Arrivals Today', $todayLate)
                ->description('Late check-ins today')
                ->descriptionIcon('heroicon-m-clock')
                ->color($todayLate > 0 ? 'warning' : 'success'),

            Stat::make('Monthly Attendance', $monthlyAttendance)
                ->description('Total attendance this month')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Pending Leaves', $pendingLeaves)
                ->description('Leave requests awaiting approval')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($pendingLeaves > 0 ? 'warning' : 'success'),

            Stat::make('Total Employees', $totalEmployees)
                ->description('Active employees')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }
}
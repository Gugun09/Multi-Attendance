<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AttendanceChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Weekly Attendance Overview';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $user = auth()->user();
        
        // Get last 7 days
        $dates = collect();
        for ($i = 6; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }

        $attendanceQuery = Attendance::query();
        
        if (!$user->hasRole('super_admin')) {
            $attendanceQuery->where('tenant_id', $user->tenant_id);
            
            if ($user->hasRole('employee')) {
                $attendanceQuery->where('user_id', $user->id);
            }
        }

        $attendanceData = $attendanceQuery
            ->select(
                DB::raw('DATE(check_in_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_count')
            )
            ->whereIn(DB::raw('DATE(check_in_at)'), $dates)
            ->groupBy(DB::raw('DATE(check_in_at)'))
            ->get()
            ->keyBy('date');

        $totalAttendance = [];
        $lateAttendance = [];
        $labels = [];

        foreach ($dates as $date) {
            $labels[] = now()->createFromFormat('Y-m-d', $date)->format('M d');
            $totalAttendance[] = $attendanceData->get($date)?->total ?? 0;
            $lateAttendance[] = $attendanceData->get($date)?->late_count ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Attendance',
                    'data' => $totalAttendance,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Late Arrivals',
                    'data' => $lateAttendance,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
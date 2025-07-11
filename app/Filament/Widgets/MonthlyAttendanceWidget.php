<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MonthlyAttendanceWidget extends ChartWidget
{
    protected static ?string $heading = 'Monthly Attendance Trends';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $user = auth()->user();
        
        // Get last 6 months
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
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
                DB::raw('DATE_FORMAT(check_in_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent')
            )
            ->whereIn(DB::raw('DATE_FORMAT(check_in_at, "%Y-%m")'), $months)
            ->groupBy(DB::raw('DATE_FORMAT(check_in_at, "%Y-%m")'))
            ->get()
            ->keyBy('month');

        $presentData = [];
        $lateData = [];
        $absentData = [];
        $labels = [];

        foreach ($months as $month) {
            $labels[] = now()->createFromFormat('Y-m', $month)->format('M Y');
            $data = $attendanceData->get($month);
            $presentData[] = $data?->present ?? 0;
            $lateData[] = $data?->late ?? 0;
            $absentData[] = $data?->absent ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $presentData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                ],
                [
                    'label' => 'Late',
                    'data' => $lateData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                ],
                [
                    'label' => 'Absent',
                    'data' => $absentData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
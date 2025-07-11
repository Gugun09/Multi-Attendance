<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\User;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopLateEmployeesWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Late Employees (This Month)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $thisMonth = now()->format('Y-m');

        $query = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(attendances.id) as total_attendance'),
                DB::raw('SUM(CASE WHEN attendances.is_late = 1 THEN 1 ELSE 0 END) as late_count'),
                DB::raw('AVG(attendances.late_minutes) as avg_late_minutes'),
                DB::raw('MAX(attendances.late_minutes) as max_late_minutes')
            ])
            ->leftJoin('attendances', function($join) use ($thisMonth) {
                $join->on('users.id', '=', 'attendances.user_id')
                     ->where('attendances.check_in_at', 'like', $thisMonth . '%');
            })
            ->whereHas('roles', function($q) {
                $q->where('name', 'employee');
            })
            ->groupBy('users.id', 'users.name', 'users.email')
            ->having('late_count', '>', 0)
            ->orderByDesc('late_count');

        if (!$user->hasRole('super_admin')) {
            $query->where('users.tenant_id', $user->tenant_id);
            
            if ($user->hasRole('employee')) {
                $query->where('users.id', $user->id);
            }
        }

        return $query;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('Employee Name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('total_attendance')
                ->label('Total Days')
                ->sortable(),

            Tables\Columns\TextColumn::make('late_count')
                ->label('Late Days')
                ->badge()
                ->color('warning')
                ->sortable(),

            Tables\Columns\TextColumn::make('late_percentage')
                ->label('Late %')
                ->getStateUsing(function ($record) {
                    if ($record->total_attendance == 0) return '0%';
                    return round(($record->late_count / $record->total_attendance) * 100, 1) . '%';
                })
                ->badge()
                ->color(fn ($state) => match (true) {
                    (float) str_replace('%', '', $state) >= 20 => 'danger',
                    (float) str_replace('%', '', $state) >= 10 => 'warning',
                    default => 'success',
                }),

            Tables\Columns\TextColumn::make('avg_late_minutes')
                ->label('Avg Late (min)')
                ->getStateUsing(fn ($record) => $record->avg_late_minutes ? round($record->avg_late_minutes, 1) : 0)
                ->suffix(' min')
                ->sortable(),

            Tables\Columns\TextColumn::make('max_late_minutes')
                ->label('Max Late (min)')
                ->suffix(' min')
                ->badge()
                ->color('danger')
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view_details')
                ->label('View Details')
                ->icon('heroicon-m-eye')
                ->url(fn ($record) => route('filament.admin.resources.attendances.index', [
                    'tableFilters[user_id][value]' => $record->id
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
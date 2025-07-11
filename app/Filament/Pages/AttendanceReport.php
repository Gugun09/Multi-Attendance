<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Attendance Reports';
    protected static ?string $navigationGroup = 'Reports';
    protected static string $view = 'filament.pages.attendance-report';

    public $startDate;
    public $endDate;
    public $selectedUser;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->live(),

                        Forms\Components\DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now()->endOfMonth())
                            ->live(),

                        Forms\Components\Select::make('selectedUser')
                            ->label('Employee (Optional)')
                            ->options(function () {
                                $query = User::whereHas('roles', function($q) {
                                    $q->where('name', 'employee');
                                });

                                if (!auth()->user()->hasRole('super_admin')) {
                                    $query->where('tenant_id', auth()->user()->tenant_id);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->live(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_days')
                    ->label('Total Days')
                    ->sortable(),

                Tables\Columns\TextColumn::make('present_days')
                    ->label('Present')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_days')
                    ->label('Late')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('absent_days')
                    ->label('Absent')
                    ->badge()
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('attendance_rate')
                    ->label('Attendance %')
                    ->getStateUsing(function ($record) {
                        if ($record->total_days == 0) return '0%';
                        return round((($record->present_days + $record->late_days) / $record->total_days) * 100, 1) . '%';
                    })
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (float) str_replace('%', '', $state) >= 95 => 'success',
                        (float) str_replace('%', '', $state) >= 80 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('avg_work_hours')
                    ->label('Avg Work Hours')
                    ->getStateUsing(function ($record) {
                        return $record->avg_work_minutes ? 
                            gmdate('H:i', $record->avg_work_minutes * 60) : 'N/A';
                    }),

                Tables\Columns\TextColumn::make('total_late_minutes')
                    ->label('Total Late (min)')
                    ->suffix(' min')
                    ->sortable(),
            ])
            ->filters([
                // Filters sudah dihandle di form
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-m-eye')
                    ->url(fn ($record) => route('filament.admin.resources.attendances.index', [
                        'tableFilters[user_id][value]' => $record->user_id,
                        'tableFilters[date][date]' => $this->startDate,
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        $query = User::query()
            ->select([
                'users.id as user_id',
                'users.name',
                'users.email',
                DB::raw('COUNT(attendances.id) as total_days'),
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_days'),
                DB::raw('SUM(CASE WHEN attendances.status = "late" THEN 1 ELSE 0 END) as late_days'),
                DB::raw('SUM(CASE WHEN attendances.status = "absent" THEN 1 ELSE 0 END) as absent_days'),
                DB::raw('SUM(attendances.late_minutes) as total_late_minutes'),
                DB::raw('AVG(TIME_TO_SEC(attendances.actual_work_hours) / 60) as avg_work_minutes')
            ])
            ->leftJoin('attendances', function($join) {
                $join->on('users.id', '=', 'attendances.user_id');
                
                if ($this->startDate) {
                    $join->where('attendances.check_in_at', '>=', $this->startDate);
                }
                
                if ($this->endDate) {
                    $join->where('attendances.check_in_at', '<=', $this->endDate . ' 23:59:59');
                }
            })
            ->whereHas('roles', function($q) {
                $q->where('name', 'employee');
            })
            ->groupBy('users.id', 'users.name', 'users.email');

        if (!$user->hasRole('super_admin')) {
            $query->where('users.tenant_id', $user->tenant_id);
        }

        if ($this->selectedUser) {
            $query->where('users.id', $this->selectedUser);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }
}
<?php

namespace App\Filament\Widgets;

use App\Models\Leave;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequestsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Leave Requests';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        $query = Leave::query()
            ->with(['user', 'approver'])
            ->latest()
            ->limit(10);

        if (!$user->hasRole('super_admin')) {
            $query->where('tenant_id', $user->tenant_id);
            
            if ($user->hasRole('employee')) {
                $query->where('user_id', $user->id);
            }
        }

        return $query;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('user.name')
                ->label('Employee')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'annual' => 'success',
                    'sick' => 'warning',
                    'personal' => 'info',
                    'maternity' => 'primary',
                    'paternity' => 'primary',
                    'unpaid' => 'gray',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('start_date')
                ->date()
                ->sortable(),

            Tables\Columns\TextColumn::make('end_date')
                ->date()
                ->sortable(),

            Tables\Columns\TextColumn::make('days_count')
                ->label('Days')
                ->getStateUsing(function ($record) {
                    return $record->start_date->diffInDays($record->end_date) + 1;
                })
                ->suffix(' days'),

            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Requested')
                ->since()
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view')
                ->label('View')
                ->icon('heroicon-m-eye')
                ->url(fn ($record) => route('filament.admin.resources.leaves.edit', $record))
                ->visible(fn () => auth()->user()->hasRole(['super_admin', 'admin'])),

            Tables\Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-m-check')
                ->color('success')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                    ]);
                })
                ->requiresConfirmation()
                ->visible(fn ($record) => 
                    $record->status === 'pending' && 
                    auth()->user()->hasRole(['super_admin', 'admin'])
                ),

            Tables\Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-m-x-mark')
                ->color('danger')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'rejected',
                        'approved_by' => auth()->id(),
                    ]);
                })
                ->requiresConfirmation()
                ->visible(fn ($record) => 
                    $record->status === 'pending' && 
                    auth()->user()->hasRole(['super_admin', 'admin'])
                ),
        ];
    }
}
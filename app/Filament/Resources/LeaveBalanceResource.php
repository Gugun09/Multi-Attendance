<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveBalanceResource\Pages;
use App\Models\LeaveBalance;
use App\Services\LeaveBalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeaveBalanceResource extends Resource
{
    protected static ?string $model = LeaveBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Leave Management';
    protected static ?string $navigationLabel = 'Leave Balances';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship(
                        'user',
                        'name',
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query
                            : $query->where('tenant_id', auth()->user()->tenant_id)
                    )
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('leave_type_id')
                    ->relationship(
                        'leaveType',
                        'name',
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query
                            : $query->where('tenant_id', auth()->user()->tenant_id)
                    )
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('policy_year')
                    ->required()
                    ->default(now()->year)
                    ->numeric(),

                Forms\Components\TextInput::make('entitled_days')
                    ->numeric()
                    ->step(0.5)
                    ->suffix('days')
                    ->required(),

                Forms\Components\TextInput::make('used_days')
                    ->numeric()
                    ->step(0.5)
                    ->suffix('days')
                    ->default(0),

                Forms\Components\TextInput::make('pending_days')
                    ->numeric()
                    ->step(0.5)
                    ->suffix('days')
                    ->default(0),

                Forms\Components\TextInput::make('carried_over_days')
                    ->numeric()
                    ->step(0.5)
                    ->suffix('days')
                    ->default(0),

                Forms\Components\TextInput::make('adjustment_days')
                    ->numeric()
                    ->step(0.5)
                    ->suffix('days')
                    ->default(0)
                    ->helperText('Manual adjustments (positive or negative)'),

                Forms\Components\Textarea::make('calculation_details')
                    ->label('Calculation Notes')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('Leave Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Annual Leave' => 'success',
                        'Sick Leave' => 'warning',
                        'Personal Leave' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('policy_year')
                    ->sortable(),

                Tables\Columns\TextColumn::make('entitled_days')
                    ->suffix(' days')
                    ->sortable(),

                Tables\Columns\TextColumn::make('used_days')
                    ->suffix(' days')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pending_days')
                    ->suffix(' days')
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_days')
                    ->suffix(' days')
                    ->color('success')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->calculateAvailableDays()),

                Tables\Columns\TextColumn::make('carried_over_days')
                    ->suffix(' days')
                    ->color('info')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_calculated_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship(
                        'user',
                        'name',
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query
                            : $query->where('tenant_id', auth()->user()->tenant_id)
                    )
                    ->searchable()
                    ->preload()
                    ->label('Employee'),

                Tables\Filters\SelectFilter::make('leave_type_id')
                    ->relationship(
                        'leaveType',
                        'name',
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query
                            : $query->where('tenant_id', auth()->user()->tenant_id)
                    )
                    ->searchable()
                    ->preload()
                    ->label('Leave Type'),

                Tables\Filters\SelectFilter::make('policy_year')
                    ->options([
                        (string)(now()->year - 1) => now()->year - 1,
                        (string)now()->year => now()->year,
                        (string)(now()->year + 1) => now()->year + 1,
                    ])
                    ->default((string)now()->year),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust')
                    ->label('Adjust Balance')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('adjustment_days')
                            ->label('Adjustment Days')
                            ->numeric()
                            ->step(0.5)
                            ->required()
                            ->helperText('Use positive numbers to add days, negative to deduct'),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Adjustment')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (array $data, LeaveBalance $record): void {
                        LeaveBalanceService::adjustBalance(
                            $record,
                            $data['adjustment_days'],
                            $data['reason'],
                            auth()->user()
                        );
                    })
                    ->visible(fn () => auth()->user()->hasRole(['super_admin', 'admin'])),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('initialize_balances')
                    ->label('Initialize Balances')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('user_ids')
                            ->label('Employees')
                            ->multiple()
                            ->relationship(
                                'user',
                                'name',
                                fn (Builder $query) => auth()->user()->hasRole('super_admin')
                                    ? $query
                                    : $query->where('tenant_id', auth()->user()->tenant_id)
                            )
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('policy_year')
                            ->label('Policy Year')
                            ->default(now()->year)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        foreach ($data['user_ids'] as $userId) {
                            $user = \App\Models\User::find($userId);
                            LeaveBalanceService::initializeUserBalances($user, $data['policy_year']);
                        }
                    })
                    ->visible(fn () => auth()->user()->hasRole(['super_admin', 'admin'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveBalances::route('/'),
            'create' => Pages\CreateLeaveBalance::route('/create'),
            'edit' => Pages\EditLeaveBalance::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        if (auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
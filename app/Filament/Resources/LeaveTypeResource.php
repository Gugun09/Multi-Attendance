<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveTypeResource\Pages;
use App\Models\LeaveType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Leave Management';
    protected static ?string $navigationLabel = 'Leave Types';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Annual Leave'),

                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g., ANNUAL')
                    ->helperText('Unique code for this leave type'),

                Forms\Components\Textarea::make('description')
                    ->maxLength(500)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('default_quota_days')
                    ->numeric()
                    ->default(12)
                    ->suffix('days')
                    ->helperText('Default annual quota for this leave type'),

                Forms\Components\TextInput::make('max_consecutive_days')
                    ->numeric()
                    ->nullable()
                    ->suffix('days')
                    ->helperText('Maximum consecutive days allowed (leave empty for no limit)'),

                Forms\Components\TextInput::make('min_notice_days')
                    ->numeric()
                    ->default(0)
                    ->suffix('days')
                    ->helperText('Minimum notice required before taking leave'),

                Forms\Components\Toggle::make('requires_approval')
                    ->default(true)
                    ->helperText('Whether this leave type requires approval'),

                Forms\Components\Toggle::make('is_paid')
                    ->default(true)
                    ->helperText('Whether this is a paid leave'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->helperText('Whether this leave type is currently active'),

                Forms\Components\Section::make('Carry-over Rules')
                    ->schema([
                        Forms\Components\Toggle::make('carry_over_rules.enabled')
                            ->label('Enable Carry-over')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('carry_over_rules.max_days')
                            ->label('Maximum Carry-over Days')
                            ->numeric()
                            ->default(5)
                            ->suffix('days')
                            ->visible(fn (Forms\Get $get) => $get('carry_over_rules.enabled')),

                        Forms\Components\TextInput::make('carry_over_rules.expiry_months')
                            ->label('Carry-over Expiry (Months)')
                            ->numeric()
                            ->default(3)
                            ->suffix('months')
                            ->visible(fn (Forms\Get $get) => $get('carry_over_rules.enabled')),

                        Forms\Components\Toggle::make('carry_over_rules.auto_expire')
                            ->label('Auto-expire Carry-over')
                            ->default(true)
                            ->visible(fn (Forms\Get $get) => $get('carry_over_rules.enabled')),
                    ])->columns(2),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('default_quota_days')
                    ->suffix(' days')
                    ->sortable(),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_paid')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\TernaryFilter::make('requires_approval')
                    ->label('Requires Approval'),
                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Paid Leave'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveTypes::route('/'),
            'create' => Pages\CreateLeaveType::route('/create'),
            'edit' => Pages\EditLeaveType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        return false;
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
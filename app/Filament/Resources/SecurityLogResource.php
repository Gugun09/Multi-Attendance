<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityLogResource\Pages;
use App\Models\SecurityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SecurityLogResource extends Resource
{
    protected static ?string $model = SecurityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Security';
    protected static ?string $navigationLabel = 'Security Logs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('event_type')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('ip_address')
                    ->required()
                    ->maxLength(45),

                Forms\Components\Textarea::make('user_agent')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'suspicious' => 'Suspicious',
                    ])
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Forms\Components\KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login' => 'success',
                        'logout' => 'info',
                        '2fa_enabled' => 'success',
                        '2fa_disabled' => 'warning',
                        '2fa_failed' => 'danger',
                        'password_changed' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'suspicious' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn ($state): string => $state),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'login' => 'Login',
                        'logout' => 'Logout',
                        '2fa_enabled' => '2FA Enabled',
                        '2fa_disabled' => '2FA Disabled',
                        '2fa_failed' => '2FA Failed',
                        'password_changed' => 'Password Changed',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'suspicious' => 'Suspicious',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityLogs::route('/'),
            'view' => Pages\ViewSecurityLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Security logs should not be manually created
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Security logs should not be edited
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        if (auth()->user()->tenant_id) {
            $query->whereHas('user', function (Builder $q) {
                $q->where('tenant_id', auth()->user()->tenant_id);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str; // Import Str untuk slug
use Filament\Forms\Components\KeyValue; // Import KeyValue
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2'; // Mengubah ikon agar lebih sesuai
    protected static ?string $navigationGroup = 'Master Data'; // Mengelompokkan navigasi

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true) // Membuat field 'name' reaktif
                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    // Otomatis generate slug saat membuat record baru
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true), // Pastikan slug unik (saat edit, abaikan record ini sendiri)
                Forms\Components\TextInput::make('domain')
                    ->maxLength(255)
                    ->nullable(), // Mengubahnya menjadi nullable sesuai migrasi
                Forms\Components\KeyValue::make('settings')
                    ->label('Tenant Settings')
                    ->keyLabel('Setting Key')
                    ->valueLabel('Setting Value')
                    ->nullable(), // Mengubahnya menjadi nullable sesuai migrasi

                Forms\Components\Section::make('Working Hours')
                    ->schema([
                        Forms\Components\TimePicker::make('work_start_time')
                            ->default('08:00')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\TimePicker::make('work_end_time')
                            ->default('17:00')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\TextInput::make('late_tolerance_minutes')
                            ->numeric()
                            ->default(15)
                            ->suffix('minutes')
                            ->required(),

                        Forms\Components\CheckboxList::make('working_days')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->default(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Break Time')
                    ->schema([
                        Forms\Components\TimePicker::make('break_start_time')
                            ->seconds(false),

                        Forms\Components\TimePicker::make('break_end_time')
                            ->seconds(false),

                        Forms\Components\TextInput::make('break_duration_minutes')
                            ->numeric()
                            ->default(60)
                            ->suffix('minutes'),
                    ])->columns(3),

                Forms\Components\Section::make('Geofencing')
                    ->schema([
                        Forms\Components\Toggle::make('enforce_geofencing')
                            ->label('Enable Geofencing')
                            ->helperText('Require employees to be within office radius for attendance'),

                        Forms\Components\TextInput::make('office_latitude')
                            ->numeric()
                            ->step(0.00000001)
                            ->placeholder('-6.200000')
                            ->helperText('Office latitude coordinate'),

                        Forms\Components\TextInput::make('office_longitude')
                            ->numeric()
                            ->step(0.00000001)
                            ->placeholder('106.816666')
                            ->helperText('Office longitude coordinate'),

                        Forms\Components\TextInput::make('geofence_radius_meters')
                            ->numeric()
                            ->default(100)
                            ->suffix('meters')
                            ->helperText('Maximum distance from office for valid attendance'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('settings')
                    ->label('Settings')
                    ->toggleable(isToggledHiddenByDefault: true), // Bisa disembunyikan secara default
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Anda bisa menambahkan filter di sini, misalnya filter berdasarkan nama atau domain
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

    public static function getRelations(): array
    {
        return [
            // Jika ada relasi yang ingin ditampilkan di sini, contoh: RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    // --- Otorisasi dengan Filament Shield ---
    // Hanya user dengan peran 'super_admin' yang boleh mengelola tenant
    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // TenantResource tidak memerlukan getEloquentQuery() custom
    // karena super_admin memang harus melihat semua tenant.
}
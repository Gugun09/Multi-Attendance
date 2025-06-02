<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Hapus jika tidak digunakan
use Filament\Forms\Components\DateTimePicker; // Untuk tanggal dan waktu
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ExportAction;
use App\Filament\Exports\AttendanceExporter;
use Filament\Tables\Actions\ExportBulkAction;
class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print'; // Mengubah ikon agar lebih sesuai
    protected static ?string $navigationGroup = 'Absensi'; // Mengelompokkan navigasi

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship(
                        'user',
                        'name',
                        // Filter user yang bisa dipilih hanya dari tenant yang sama
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query // Superadmin melihat semua user
                            : $query->where('tenant_id', auth()->user()->tenant_id) // Admin/Employee hanya melihat user di tenantnya
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Employee')
                    // Sembunyikan field ini dari Employee saat membuat atau mengedit absensi
                    ->hiddenOn(['create', 'edit'], fn () => auth()->user()->hasRole('employee'))
                    // Isi otomatis dengan ID user yang login jika role adalah employee
                    ->default(fn () => auth()->user()->hasRole('employee') ? auth()->id() : null),

                Forms\Components\DateTimePicker::make('check_in_at')
                    ->label('Check In Time')
                    ->required()
                    ->default(now()) // Default ke waktu sekarang
                    ->native(false), // Opsional: gunakan datetime picker kustom Filament

                Forms\Components\DateTimePicker::make('check_out_at')
                    ->label('Check Out Time')
                    ->nullable() // Check out bisa null jika absensi belum selesai
                    ->native(false)
                    ->afterOrEqual('check_in_at'), // Check out harus setelah atau sama dengan check in

                Forms\Components\TextInput::make('check_in_location')
                    ->label('Check In Location')
                    ->maxLength(255)
                    ->nullable(), // Lokasi bisa null

                Forms\Components\TextInput::make('check_out_location')
                    ->label('Check Out Location')
                    ->maxLength(255)
                    ->nullable(), // Lokasi bisa null

                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->nullable(), // Catatan bisa null

                Forms\Components\Select::make('status')
                    ->options([
                        'present' => 'Present',
                        'late' => 'Late',
                        'absent' => 'Absent',
                        'on_leave' => 'On Leave',
                    ])
                    ->default('present')
                    ->required()
                    // Hanya Admin/Superadmin yang bisa mengubah status secara manual
                    ->visible(fn () => auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin')),
            ])->columns(1); // Mengatur layout form menjadi satu kolom
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name') // Menampilkan nama karyawan dari relasi
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in_at')
                    ->dateTime() // Format sebagai tanggal dan waktu
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_at')
                    ->dateTime() // Format sebagai tanggal dan waktu
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge() // Menampilkan status sebagai badge
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'late' => 'warning',
                        'absent' => 'danger',
                        'on_leave' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in_location')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Bisa disembunyikan
                Tables\Columns\TextColumn::make('check_out_location')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Bisa disembunyikan
                Tables\Columns\TextColumn::make('notes')
                    ->limit(50) // Batasi teks notes agar tidak terlalu panjang
                    ->tooltip(fn ($state): string => $state) // Tampilkan tooltip untuk notes penuh
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship(
                        'user',
                        'name',
                        // Filter user di filter berdasarkan tenant yang login
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query
                            : $query->where('tenant_id', auth()->user()->tenant_id)
                    )
                    ->searchable()
                    ->preload()
                    ->label('Filter by Employee')
                    // Hanya Superadmin/Admin yang bisa filter semua user
                    ->visible(fn () => auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin')),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'present' => 'Present',
                        'late' => 'Late',
                        'absent' => 'Absent',
                        'on_leave' => 'On Leave',
                    ])
                    ->label('Filter by Status')
                    ->default('present'), // Default filter ke status 'present'

                Tables\Filters\Filter::make('date') // Filter berdasarkan tanggal check_in_at
                    ->form([
                        Forms\Components\DatePicker::make('date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('check_in_at', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                ExportAction::make()->exporter(AttendanceExporter::class)->label('Export Attendance')->icon('heroicon-o-arrow-down-on-square')->color('success')

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()->exporter(AttendanceExporter::class)->label('Export Attendance')->icon('heroicon-o-arrow-down-on-square')->color('success')
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Jika ada relasi lain yang ingin ditampilkan di sini
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }

    // --- Otorisasi dengan Filament Shield ---
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_attendance');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_attendance');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Superadmin selalu bisa edit
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        // Admin bisa edit absensi di tenant-nya
        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        // Karyawan hanya bisa edit absensi mereka sendiri
        if (auth()->user()->hasRole('employee') && $record->user_id === auth()->id()) {
            return true;
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Superadmin selalu bisa delete
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        // Admin bisa delete absensi di tenant-nya
        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        // Karyawan hanya bisa delete absensi mereka sendiri
        if (auth()->user()->hasRole('employee') && $record->user_id === auth()->id()) {
            return true;
        }

        return false;
    }

    // --- Logika Multi-Tenancy (Filter Query) ---
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Superadmin melihat semua absensi
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        // Admin dan Employee hanya melihat absensi di tenant mereka
        if (auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);

            // Employee hanya melihat absensi mereka sendiri
            if (auth()->user()->hasRole('employee')) {
                $query->where('user_id', auth()->id());
            }
        } else {
            // Jika ada user yang login tapi tidak memiliki tenant_id dan bukan superadmin
            // Ini akan memastikan mereka tidak melihat absensi lain (menampilkan 0 hasil)
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
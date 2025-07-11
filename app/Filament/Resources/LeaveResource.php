<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveResource\Pages;
use App\Models\Leave;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Hapus jika tidak digunakan
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use App\Filament\Exports\LeaveExporter;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days'; // Mengubah ikon agar lebih sesuai
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
                    // Sembunyikan field ini dari Employee saat membuat permintaan cuti, otomatis isi dengan user yang login
                    ->hiddenOn('create', fn () => auth()->user()->hasRole('employee'))
                    // Isi otomatis dengan ID user yang login jika role adalah employee
                    ->default(fn () => auth()->user()->hasRole('employee') ? auth()->id() : null),

                Forms\Components\Select::make('type') // Menambahkan field jenis cuti
                    ->options([
                        'annual' => 'Annual Leave',
                        'sick' => 'Sick Leave',
                        'personal' => 'Personal Leave',
                        'maternity' => 'Maternity Leave',
                        'paternity' => 'Paternity Leave',
                        'unpaid' => 'Unpaid Leave',
                    ])
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->required()
                    ->native(false), // Opsional: gunakan picker kustom Filament
                Forms\Components\DatePicker::make('end_date')
                    ->required()
                    ->afterOrEqual('start_date') // Validasi: end_date tidak boleh sebelum start_date
                    ->native(false), // Opsional: gunakan picker kustom Filament

                Forms\Components\Textarea::make('reason')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->required()
                    // Field status hanya bisa diubah oleh Admin atau Superadmin
                    ->visible(fn (string $operation) => $operation !== 'create' && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))),

                Forms\Components\Select::make('approved_by')
                    ->relationship(
                        'approver', // Pastikan relasi 'approver' ada di model Leave (public function approver() { return $this->belongsTo(User::class, 'approved_by'); })
                        'name',
                        // Filter approver agar hanya dari tenant yang sama
                        fn (Builder $query) => auth()->user()->hasRole('super_admin')
                            ? $query // Superadmin melihat semua user
                            : $query->where('tenant_id', auth()->user()->tenant_id) // Admin melihat user di tenantnya
                    )
                    ->label('Approved By')
                    ->nullable() // Approved_by bisa null jika status masih pending
                    // Field ini hanya terlihat oleh Admin atau Superadmin
                    ->visible(fn (string $operation) => $operation !== 'create' && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))),

                Forms\Components\Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->maxLength(65535)
                    ->nullable() // Admin notes bisa null
                    ->columnSpanFull()
                    // Field ini hanya terlihat oleh Admin atau Superadmin
                    ->visible(fn (string $operation) => $operation !== 'create' && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin'))),
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
                Tables\Columns\TextColumn::make('type') // Kolom jenis cuti
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date() // Format sebagai tanggal
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date() // Format sebagai tanggal
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(50) // Batasi teks alasan agar tidak terlalu panjang
                    ->tooltip(fn ($state): string => $state), // Tampilkan tooltip untuk alasan penuh
                Tables\Columns\TextColumn::make('status')
                    ->badge() // Menampilkan status sebagai badge
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approver.name') // Menampilkan nama approver dari relasi
                    ->label('Approved By')
                    ->placeholder('N/A') // Placeholder jika belum disetujui
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('admin_notes')
                    ->limit(50)
                    ->tooltip(fn ($state): string => $state)
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

                Tables\Filters\SelectFilter::make('type') // Filter berdasarkan jenis cuti
                    ->options([
                        'annual' => 'Annual Leave',
                        'sick' => 'Sick Leave',
                        'personal' => 'Personal Leave',
                        'maternity' => 'Maternity Leave',
                        'paternity' => 'Paternity Leave',
                        'unpaid' => 'Unpaid Leave',
                    ])
                    ->label('Filter by Type'),

                Tables\Filters\SelectFilter::make('status') // Filter berdasarkan status cuti
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->label('Filter by Status')
                    ->default('pending'), // Opsional: default filter ke status 'pending'

                Tables\Filters\Filter::make('date_range') // Menggabungkan filter start_date dan end_date menjadi satu
                    ->form([
                        DatePicker::make('start_date')
                            ->label('Start Date (From)'),
                        DatePicker::make('end_date')
                            ->label('End Date (To)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['end_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('end_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (\Illuminate\Database\Eloquent\Model $record): bool =>
                        // Admin/Superadmin bisa edit jika status pending atau mereka yang menyetujui
                        ((auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin')) && $record->status === 'pending') ||
                        // Karyawan bisa edit pengajuan cuti mereka sendiri jika status masih pending
                        ($record->user_id === auth()->id() && $record->status === 'pending')
                    ),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (\Illuminate\Database\Eloquent\Model $record): bool =>
                        // Admin/Superadmin bisa menghapus
                        (auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin')) ||
                        // Karyawan bisa menghapus pengajuan cuti mereka sendiri jika status masih pending
                        ($record->user_id === auth()->id() && $record->status === 'pending')
                    ),
            ])
            ->headerActions([
                ExportAction::make()->exporter(LeaveExporter::class)->label('Export Leave')->icon('heroicon-o-arrow-down-on-square')->color('success')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('admin') || auth()->user()->hasRole('super_admin')),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()->exporter(LeaveExporter::class)->label('Export Leave')->icon('heroicon-o-arrow-down-on-square')->color('success')
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
            'index' => Pages\ListLeaves::route('/'),
            'create' => Pages\CreateLeave::route('/create'),
            'edit' => Pages\EditLeave::route('/{record}/edit'),
        ];
    }

    // --- Otorisasi dengan Filament Shield ---
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_leave');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_leave');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Superadmin selalu bisa edit
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        // Admin bisa edit cuti di tenant-nya, terutama jika pending
        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        // Karyawan bisa edit cuti mereka sendiri jika statusnya masih pending
        if (auth()->user()->hasRole('employee') && $record->user_id === auth()->id() && $record->status === 'pending') {
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

        // Admin bisa delete cuti di tenant-nya
        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        // Karyawan bisa delete cuti mereka sendiri jika statusnya masih pending
        if (auth()->user()->hasRole('employee') && $record->user_id === auth()->id() && $record->status === 'pending') {
            return true;
        }

        return false;
    }

    // --- Logika Multi-Tenancy (Filter Query) ---
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Superadmin melihat semua cuti
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        // Admin dan Employee hanya melihat cuti di tenant mereka
        if (auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);

            // Employee hanya melihat cuti mereka sendiri
            if (auth()->user()->hasRole('employee')) {
                $query->where('user_id', auth()->id());
            }
        } else {
            // Jika ada user yang login tapi tidak memiliki tenant_id dan bukan superadmin
            // Ini akan memastikan mereka tidak melihat cuti lain (menampilkan 0 hasil)
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Superadmin melihat semua cuti
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        // Admin dan Employee hanya melihat cuti di tenant mereka
        if (auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);

            // Employee hanya melihat cuti mereka sendiri
            if (auth()->user()->hasRole('employee')) {
                $query->where('user_id', auth()->id());
            }
        } else {
            // Jika ada user yang login tapi tidak memiliki tenant_id dan bukan superadmin
            // Ini akan memastikan mereka tidak melihat cuti lain (menampilkan 0 hasil)
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
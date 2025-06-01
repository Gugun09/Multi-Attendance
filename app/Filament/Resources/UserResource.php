<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash; // Import untuk hashing password
use Filament\Forms\Components\Select; // Import ini
use Filament\Tables\Columns\TextColumn; // Import ini

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users'; // Ganti ikon agar lebih sesuai
    protected static ?string $navigationGroup = 'Master Data'; // Grouping navigasi

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true), // Pastikan email unik saat update juga
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)) // Otomatis hash password
                    ->dehydrated(fn (?string $state): bool => filled($state)) // Hanya dehidrasi jika password diisi
                    ->required(fn (string $operation): bool => $operation === 'create') // Required hanya saat membuat user baru
                    ->maxLength(255)
                    ->label('Password (leave blank to keep current)'), // Label yang lebih jelas
                // Field 'tenant_id'
                Forms\Components\Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->label('Company / Tenant')
                    ->placeholder('Select a Tenant (leave empty for Superadmin)')
                    ->helperText('Superadmin users do not belong to any tenant.')
                    ->nullable() // Penting: Superadmin tidak memiliki tenant_id
                    ->columnSpan('full')
                    // Sembunyikan field ini dari Admin, Admin hanya bisa kelola user di tenant-nya
                    ->hidden(fn () => auth()->user()->hasRole('admin')),
                // Field 'roles' menggunakan relasi Spatie Permission
                Forms\Components\Select::make('roles')
                    ->multiple()
                    ->relationship(
                        'roles', // Nama relasi
                        'name',  // Kolom yang akan ditampilkan sebagai opsi
                        // Argumen ketiga: Closure untuk memodifikasi query relasi
                        fn (Builder $query) => $query->where('name', '!=', 'super_admin')
                    )
                    ->preload()
                    ->searchable()
                    ->columnSpan('full')
                    ->visible(fn () => auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')),
            ])->columns(2); // Menata form menjadi 2 kolom
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('N/A (Superadmin)') // Tampilan untuk user tanpa tenant
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name') // Menampilkan roles dari relasi
                    ->label('Roles')
                    ->badge() // Menampilkan roles sebagai badge
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'employee' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Bisa disembunyikan
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->label('Filter by Tenant')
                    ->searchable()
                    ->preload()
                    ->hidden(fn () => auth()->user()->hasRole('admin')), // Hanya Superadmin yang bisa filter tenant
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('roles', 'name') // Filter berdasarkan relasi roles
                    ->label('Filter by Role')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->label('Filter by Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    // --- Otorisasi dengan Filament Shield ---
    // Memberikan izin akses ke Resource ini berdasarkan peran atau permission
    public static function canViewAny(): bool
    {
        // Siapa saja yang punya permission 'view_any_user' bisa melihat daftar user
        return auth()->user()->can('view_any_user');
    }

    public static function canCreate(): bool
    {
        // Siapa saja yang punya permission 'create_user' bisa membuat user
        return auth()->user()->can('create_user');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Siapa saja yang punya permission 'update_user' bisa mengedit user
        // Namun, user biasa tidak boleh mengedit superadmin, atau user dari tenant lain
        if (auth()->user()->hasRole('super_admin')) {
            return true; // Superadmin bisa edit semua
        }

        // Admin hanya bisa edit user di tenant-nya
        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }

        // Karyawan hanya bisa edit dirinya sendiri (profil)
        if (auth()->user()->hasRole('employee') && $record->id === auth()->id()) {
            return true;
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Siapa saja yang punya permission 'delete_user' bisa menghapus user
        // Batasan: Superadmin bisa delete semua (kecuali dirinya sendiri mungkin)
        // Admin hanya bisa delete user di tenant-nya, dan tidak superadmin
        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        if (auth()->user()->hasRole('admin') && $record->tenant_id === auth()->user()->tenant_id) {
            return true;
        }
        return false;
    }

    // --- Logika Multi-Tenancy (Filter Query) ---
    // Penting: Memastikan user hanya melihat data yang relevan dengan tenant mereka
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Superadmin melihat semua user
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        // Admin dan Employee hanya melihat user di tenant mereka
        if (auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);

            // Karyawan (Employee) hanya melihat dirinya sendiri di daftar user
            // Anda bisa sesuaikan ini jika karyawan juga perlu melihat karyawan lain dari tenant yang sama
            if (auth()->user()->hasRole('employee')) {
                $query->where('id', auth()->id());
            }
        } else {
            // Jika ada user yang login tapi tidak memiliki tenant_id dan bukan superadmin
            // Ini akan memastikan mereka tidak melihat user lain (menampilkan 0 hasil)
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
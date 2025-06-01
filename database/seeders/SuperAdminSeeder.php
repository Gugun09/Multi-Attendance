<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- 1. Buat Peran 'super_admin' ---
        // Pastikan peran ini dibuat sebelum diberikan kepada user.
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->command->info('Role "super_admin" created or found.');

        // --- 2. Buat Tenant Contoh (PT CountrySSH) ---
        // Ini harus dilakukan SEBELUM membuat user jika user tersebut terikat dengan tenant.
        // Jika Super Admin tidak terikat tenant, urutan ini tidak terlalu krusial untuk Super Admin,
        // tapi baik untuk demonstrasi tenant.
        $tenantCountrySSH = Tenant::updateOrCreate(
            ['slug' => 'countryssh'], // Cek berdasarkan slug untuk menghindari duplikasi
            [
                'name' => 'PT CountrySSH',
                'domain' => 'countryssh.com',
                'settings' => ['timezone' => 'Asia/Makassar'],
                // TIDAK PERLU mengisi 'id' di sini jika tenants.id adalah auto-increment.
                // Database akan mengisinya secara otomatis.
            ]
        );
        $this->command->info('Tenant created: PT CountrySSH (ID: ' . $tenantCountrySSH->id . ')');

        // --- 3. Buat User Super Admin ---
        // Super Admin TIDAK boleh memiliki tenant_id. Pastikan nilainya NULL.
        $superAdminUser = User::updateOrCreate(
            ['email' => 'gugun09@gmail.com'], // Gunakan email standar untuk superadmin
            [
                'name' => 'Super Admin', // Nama user superadmin
                'password' => Hash::make('password'), // Ganti dengan password yang KUAT!
                'email_verified_at' => now(),
                'tenant_id' => 1, // PENTING: Super Admin tidak terikat dengan tenant manapun
            ]
        );

        // --- 4. Berikan peran 'super_admin' kepada user Super Admin ---
        if (!$superAdminUser->hasRole('super_admin')) {
            $superAdminUser->assignRole('super_admin');
        }
        $this->command->info('Super Admin user created: gugun09@gmail.com / password (password)');

        // --- 5. Berikan Semua Izin ke Peran Super Admin ---
        // Penting: Izin harus sudah di-generate oleh `php artisan shield:generate --all`
        $permissions = Permission::all();
        $superAdminRole->syncPermissions($permissions); // Super Admin mendapat semua izin
        $this->command->info('All available permissions assigned to "super_admin" role.');

        $this->command->info('All seeders completed successfully!');
    }
}
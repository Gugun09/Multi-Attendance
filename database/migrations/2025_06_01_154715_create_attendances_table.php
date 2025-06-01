<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade')->onUpdate('cascade'); // FOREIGN KEY ke tenants.id
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // FOREIGN KEY ke users.id
            $table->dateTime('check_in_at')->nullable(); // Waktu check-in
            $table->dateTime('check_out_at')->nullable(); // Waktu check-out (bisa NULL jika belum check-out)
            $table->string('check_in_location')->nullable(); // Opsional: Koordinat GPS atau deskripsi lokasi check-in
            $table->string('check_out_location')->nullable(); // Opsional: Koordinat GPS atau deskripsi lokasi check-out
            $table->text('notes')->nullable(); // Catatan tambahan untuk absensi
            $table->string('status')->default('present'); // Status absensi (misal: 'present', 'late', 'absent', 'on_leave')
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

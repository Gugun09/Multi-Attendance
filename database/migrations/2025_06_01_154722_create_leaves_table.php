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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade')->onUpdate('cascade'); // FOREIGN KEY ke tenants.id
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Karyawan yang mengajukan cuti
            $table->string('type'); // Jenis cuti (misal: 'annual', 'sick', 'personal', 'unpaid')
            $table->date('start_date'); // Tanggal mulai cuti
            $table->date('end_date'); // Tanggal berakhir cuti
            $table->text('reason'); // Alasan pengajuan cuti
            $table->string('status')->default('pending'); // Status cuti ('pending', 'approved', 'rejected')
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // User yang menyetujui (Admin/Superadmin)
            $table->text('admin_notes')->nullable(); // Catatan dari admin/approver
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};

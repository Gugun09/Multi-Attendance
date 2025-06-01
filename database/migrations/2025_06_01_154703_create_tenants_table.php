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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama perusahaan/organisasi
            $table->string('slug')->unique(); // Slug unik untuk URL (misal: "pt-jaya-abadi")
            $table->string('domain')->nullable(); // Opsional: untuk kustomisasi subdomain (misal: "ptjaya.myapp.com")
            $table->json('settings')->nullable(); // Kolom JSON untuk menyimpan pengaturan spesifik tenant (misal: { "office_lat": "...", "office_long": "..." })
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

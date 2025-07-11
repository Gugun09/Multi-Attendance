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
        // Add soft deletes to main tables
        
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'users_deleted_at_index');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'tenants_deleted_at_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'attendances_deleted_at_index');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'leaves_deleted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('tenants_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex('leaves_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
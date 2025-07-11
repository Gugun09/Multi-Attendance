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
        // Add indexes for better query performance
        
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('tenant_id', 'users_tenant_id_index');
            $table->index('email', 'users_email_index');
            $table->index(['tenant_id', 'email'], 'users_tenant_email_index');
        });

        // Attendances table indexes
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('tenant_id', 'attendances_tenant_id_index');
            $table->index('user_id', 'attendances_user_id_index');
            $table->index('status', 'attendances_status_index');
            $table->index('check_in_at', 'attendances_check_in_at_index');
            $table->index('check_out_at', 'attendances_check_out_at_index');
            $table->index(['tenant_id', 'user_id'], 'attendances_tenant_user_index');
            $table->index(['tenant_id', 'check_in_at'], 'attendances_tenant_date_index');
            $table->index(['user_id', 'check_in_at'], 'attendances_user_date_index');
        });

        // Leaves table indexes
        Schema::table('leaves', function (Blueprint $table) {
            $table->index('tenant_id', 'leaves_tenant_id_index');
            $table->index('user_id', 'leaves_user_id_index');
            $table->index('status', 'leaves_status_index');
            $table->index('type', 'leaves_type_index');
            $table->index('start_date', 'leaves_start_date_index');
            $table->index('end_date', 'leaves_end_date_index');
            $table->index('approved_by', 'leaves_approved_by_index');
            $table->index(['tenant_id', 'user_id'], 'leaves_tenant_user_index');
            $table->index(['tenant_id', 'status'], 'leaves_tenant_status_index');
            $table->index(['user_id', 'start_date', 'end_date'], 'leaves_user_date_range_index');
        });

        // Tenants table indexes
        Schema::table('tenants', function (Blueprint $table) {
            $table->index('slug', 'tenants_slug_index');
            $table->index('domain', 'tenants_domain_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_id_index');
            $table->dropIndex('users_email_index');
            $table->dropIndex('users_tenant_email_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_tenant_id_index');
            $table->dropIndex('attendances_user_id_index');
            $table->dropIndex('attendances_status_index');
            $table->dropIndex('attendances_check_in_at_index');
            $table->dropIndex('attendances_check_out_at_index');
            $table->dropIndex('attendances_tenant_user_index');
            $table->dropIndex('attendances_tenant_date_index');
            $table->dropIndex('attendances_user_date_index');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex('leaves_tenant_id_index');
            $table->dropIndex('leaves_user_id_index');
            $table->dropIndex('leaves_status_index');
            $table->dropIndex('leaves_type_index');
            $table->dropIndex('leaves_start_date_index');
            $table->dropIndex('leaves_end_date_index');
            $table->dropIndex('leaves_approved_by_index');
            $table->dropIndex('leaves_tenant_user_index');
            $table->dropIndex('leaves_tenant_status_index');
            $table->dropIndex('leaves_user_date_range_index');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('tenants_slug_index');
            $table->dropIndex('tenants_domain_index');
        });
    }
};
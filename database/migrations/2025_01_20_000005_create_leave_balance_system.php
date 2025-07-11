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
        /*
          # Leave Balance System

          1. New Tables
            - `leave_types` - Master data jenis cuti dengan kuota
            - `leave_balances` - Saldo cuti per karyawan per tahun
            - `leave_policies` - Kebijakan cuti per tenant
            - `holidays` - Hari libur nasional/perusahaan

          2. Enhanced Features
            - Kuota cuti tahunan
            - Carry-over rules
            - Pro-rated calculation
            - Holiday calendar
        */

        // Leave Types Master Data
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name'); // Annual, Sick, Personal, etc.
            $table->string('code')->unique(); // ANNUAL, SICK, PERSONAL
            $table->text('description')->nullable();
            $table->integer('default_quota_days')->default(0); // Default quota per year
            $table->integer('max_consecutive_days')->nullable(); // Max consecutive days
            $table->integer('min_notice_days')->default(0); // Minimum notice required
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('carry_over_rules')->nullable(); // Rules for carry over
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index('code');
        });

        // Leave Policies per Tenant
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('policy_year'); // 2024, 2025, etc.
            $table->date('year_start_date'); // Policy year start (e.g., Jan 1 or Apr 1)
            $table->date('year_end_date'); // Policy year end
            $table->boolean('pro_rate_new_employees')->default(true); // Pro-rate for new joiners
            $table->integer('probation_period_months')->default(3); // Probation period
            $table->boolean('allow_negative_balance')->default(false);
            $table->integer('max_carry_over_days')->default(5); // Max days to carry over
            $table->date('carry_over_expiry_date')->nullable(); // When carry-over expires
            $table->json('settings')->nullable(); // Additional settings
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'policy_year']);
            $table->index(['tenant_id', 'policy_year']);
        });

        // Leave Balances per Employee
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->string('policy_year'); // 2024, 2025, etc.
            $table->decimal('entitled_days', 5, 2)->default(0); // Total entitled days
            $table->decimal('used_days', 5, 2)->default(0); // Used days
            $table->decimal('pending_days', 5, 2)->default(0); // Pending approval days
            $table->decimal('available_days', 5, 2)->default(0); // Available days
            $table->decimal('carried_over_days', 5, 2)->default(0); // From previous year
            $table->decimal('adjustment_days', 5, 2)->default(0); // Manual adjustments
            $table->date('last_calculated_at')->nullable(); // Last calculation date
            $table->json('calculation_details')->nullable(); // Calculation breakdown
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'leave_type_id', 'policy_year']);
            $table->index(['tenant_id', 'policy_year']);
            $table->index(['user_id', 'policy_year']);
        });

        // Holidays Calendar
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade'); // Null for national holidays
            $table->string('name');
            $table->date('date');
            $table->string('type')->default('public'); // public, company, religious
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false); // Annual recurring
            $table->string('country_code', 2)->default('ID'); // ISO country code
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'date']);
            $table->index(['date', 'is_active']);
            $table->index(['type', 'is_active']);
        });

        // Leave Transactions Log
        Schema::create('leave_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('leave_id')->nullable()->constrained('leaves')->onDelete('set null');
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->onDelete('cascade');
            $table->string('transaction_type'); // debit, credit, adjustment, carry_over
            $table->decimal('days', 5, 2);
            $table->string('description');
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['leave_balance_id']);
            $table->index(['transaction_type']);
            $table->index(['created_at']);
        });

        // Update leaves table to reference leave_type
        Schema::table('leaves', function (Blueprint $table) {
            $table->foreignId('leave_type_id')->nullable()->after('type')->constrained('leave_types')->onDelete('set null');
            $table->decimal('calculated_days', 5, 2)->nullable()->after('end_date'); // Calculated working days
            $table->boolean('deducted_from_balance')->default(false)->after('status');
            
            $table->index('leave_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->dropIndex(['leave_type_id']);
            $table->dropColumn(['leave_type_id', 'calculated_days', 'deducted_from_balance']);
        });

        Schema::dropIfExists('leave_transactions');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
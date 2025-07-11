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
        // Add working hours and geofencing settings to tenants
        Schema::table('tenants', function (Blueprint $table) {
            // Working Hours Settings
            $table->time('work_start_time')->default('08:00:00')->after('settings');
            $table->time('work_end_time')->default('17:00:00')->after('work_start_time');
            $table->integer('late_tolerance_minutes')->default(15)->after('work_end_time'); // Toleransi terlambat
            $table->json('working_days')->default('["monday","tuesday","wednesday","thursday","friday"]')->after('late_tolerance_minutes'); // Hari kerja
            
            // Geofencing Settings
            $table->decimal('office_latitude', 10, 8)->nullable()->after('working_days'); // Koordinat kantor
            $table->decimal('office_longitude', 11, 8)->nullable()->after('office_latitude');
            $table->integer('geofence_radius_meters')->default(100)->after('office_longitude'); // Radius dalam meter
            $table->boolean('enforce_geofencing')->default(false)->after('geofence_radius_meters'); // Aktifkan geofencing
            
            // Break Time Settings
            $table->time('break_start_time')->nullable()->after('enforce_geofencing');
            $table->time('break_end_time')->nullable()->after('break_start_time');
            $table->integer('break_duration_minutes')->default(60)->after('break_end_time');
        });

        // Add location validation to attendances
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('check_in_latitude', 10, 8)->nullable()->after('check_in_location');
            $table->decimal('check_in_longitude', 11, 8)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 8)->nullable()->after('check_out_location');
            $table->decimal('check_out_longitude', 11, 8)->nullable()->after('check_out_latitude');
            $table->boolean('is_within_geofence')->default(true)->after('check_out_longitude'); // Apakah dalam radius
            $table->decimal('distance_from_office', 8, 2)->nullable()->after('is_within_geofence'); // Jarak dari kantor (meter)
            $table->time('actual_work_hours')->nullable()->after('distance_from_office'); // Jam kerja aktual
            $table->boolean('is_late')->default(false)->after('actual_work_hours'); // Apakah terlambat
            $table->integer('late_minutes')->default(0)->after('is_late'); // Menit keterlambatan
        });

        // Create shifts table for multiple shift support
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name'); // Nama shift (e.g., "Morning Shift", "Night Shift")
            $table->time('start_time');
            $table->time('end_time');
            $table->json('working_days')->default('["monday","tuesday","wednesday","thursday","friday"]');
            $table->integer('late_tolerance_minutes')->default(15);
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Add shift assignment to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('tenant_id')->constrained('shifts')->onDelete('set null');
            $table->index('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
        });

        Schema::dropIfExists('shifts');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_latitude',
                'check_in_longitude',
                'check_out_latitude',
                'check_out_longitude',
                'is_within_geofence',
                'distance_from_office',
                'actual_work_hours',
                'is_late',
                'late_minutes'
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'work_start_time',
                'work_end_time',
                'late_tolerance_minutes',
                'working_days',
                'office_latitude',
                'office_longitude',
                'geofence_radius_meters',
                'enforce_geofencing',
                'break_start_time',
                'break_end_time',
                'break_duration_minutes'
            ]);
        });
    }
};
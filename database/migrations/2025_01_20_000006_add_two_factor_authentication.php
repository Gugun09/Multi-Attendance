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
          # Two-Factor Authentication System

          1. Enhanced Security
            - 2FA with Google Authenticator
            - Backup recovery codes
            - Security audit logs
            - Session management

          2. Features
            - QR Code generation
            - Recovery codes
            - Security notifications
            - Login attempts tracking
        */

        // Add 2FA fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->string('last_login_user_agent')->nullable()->after('last_login_ip');
            
            $table->index(['two_factor_enabled']);
            $table->index(['last_login_at']);
        });

        // Security audit logs
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type'); // login, logout, 2fa_enabled, 2fa_disabled, password_changed
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional event data
            $table->string('status')->default('success'); // success, failed, suspicious
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'event_type']);
            $table->index(['event_type', 'status']);
            $table->index(['ip_address']);
            $table->index(['created_at']);
        });

        // Login attempts tracking
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable(); // invalid_credentials, account_locked, 2fa_failed
            $table->timestamp('attempted_at');
            $table->timestamps();
            
            $table->index(['email', 'ip_address']);
            $table->index(['ip_address', 'attempted_at']);
            $table->index(['successful']);
        });

        // Active sessions tracking
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('session_id')->unique();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity');
            $table->boolean('is_current')->default(false);
            $table->json('metadata')->nullable(); // Device info, location, etc.
            $table->timestamps();
            
            $table->index(['user_id', 'last_activity']);
            $table->index(['session_id']);
            $table->index(['is_current']);
        });

        // Security settings per tenant
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->boolean('require_2fa')->default(false); // Force 2FA for all users
            $table->integer('max_login_attempts')->default(5);
            $table->integer('lockout_duration_minutes')->default(15);
            $table->boolean('notify_suspicious_login')->default(true);
            $table->json('allowed_ip_ranges')->nullable(); // IP whitelist
            $table->integer('session_timeout_minutes')->default(120);
            $table->boolean('force_password_change')->default(false);
            $table->integer('password_expiry_days')->nullable();
            $table->json('settings')->nullable(); // Additional security settings
            $table->timestamps();
            
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_settings');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('security_logs');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['two_factor_enabled']);
            $table->dropIndex(['last_login_at']);
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_enabled',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent'
            ]);
        });
    }
};
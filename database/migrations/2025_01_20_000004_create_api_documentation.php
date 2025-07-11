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
        // Create API logs table for monitoring
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('status_code');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('response_time', 8, 3)->nullable(); // in seconds
            $table->timestamps();
            
            $table->index(['method', 'endpoint']);
            $table->index(['status_code']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });

        // Create API rate limits table
        Schema::create('api_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key'); // IP or user identifier
            $table->string('endpoint');
            $table->integer('hits')->default(1);
            $table->timestamp('reset_time');
            $table->timestamps();
            
            $table->unique(['key', 'endpoint']);
            $table->index(['reset_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_rate_limits');
        Schema::dropIfExists('api_logs');
    }
};
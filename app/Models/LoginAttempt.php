<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoginAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'failure_reason',
        'attempted_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    /**
     * Log login attempt
     */
    public static function logAttempt(
        string $email,
        bool $successful,
        ?string $failureReason = null
    ): void {
        static::create([
            'email' => $email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'successful' => $successful,
            'failure_reason' => $failureReason,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Check if IP is rate limited
     */
    public static function isRateLimited(string $email, string $ipAddress, int $maxAttempts = 5): bool
    {
        $recentAttempts = static::where('email', $email)
            ->where('ip_address', $ipAddress)
            ->where('successful', false)
            ->where('attempted_at', '>', now()->subMinutes(15))
            ->count();

        return $recentAttempts >= $maxAttempts;
    }
}
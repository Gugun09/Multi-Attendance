<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'last_activity',
        'is_current',
        'metadata',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'is_current' => 'boolean',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Track user session
     */
    public static function trackSession(User $user, string $sessionId): void
    {
        // Mark all other sessions as not current
        static::where('user_id', $user->id)->update(['is_current' => false]);

        // Create or update current session
        static::updateOrCreate([
            'user_id' => $user->id,
            'session_id' => $sessionId,
        ], [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'last_activity' => now(),
            'is_current' => true,
            'metadata' => [
                'login_time' => now()->toISOString(),
                'device_type' => static::detectDeviceType(request()->userAgent()),
            ],
        ]);
    }

    /**
     * Detect device type from user agent
     */
    private static function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) return 'unknown';

        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/Tablet/', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }
}
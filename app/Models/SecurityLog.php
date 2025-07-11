<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'metadata',
        'status',
        'description',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log security event
     */
    public static function logEvent(
        string $eventType,
        ?User $user = null,
        string $status = 'success',
        ?string $description = null,
        array $metadata = []
    ): void {
        static::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'status' => $status,
            'description' => $description,
        ]);
    }
}
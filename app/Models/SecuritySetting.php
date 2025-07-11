<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SecuritySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'require_2fa',
        'max_login_attempts',
        'lockout_duration_minutes',
        'notify_suspicious_login',
        'allowed_ip_ranges',
        'session_timeout_minutes',
        'force_password_change',
        'password_expiry_days',
        'settings',
    ];

    protected $casts = [
        'require_2fa' => 'boolean',
        'notify_suspicious_login' => 'boolean',
        'allowed_ip_ranges' => 'array',
        'force_password_change' => 'boolean',
        'settings' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get security settings for tenant
     */
    public static function forTenant(int $tenantId): self
    {
        return static::firstOrCreate(['tenant_id' => $tenantId]);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'default_quota_days',
        'max_consecutive_days',
        'min_notice_days',
        'requires_approval',
        'is_paid',
        'is_active',
        'carry_over_rules',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'carry_over_rules' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    /**
     * Get carry over rules with defaults
     */
    public function getCarryOverRulesAttribute($value)
    {
        $rules = json_decode($value, true) ?? [];
        
        return array_merge([
            'enabled' => false,
            'max_days' => 5,
            'expiry_months' => 3,
            'auto_expire' => true,
        ], $rules);
    }
}
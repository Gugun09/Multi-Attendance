<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeavePolicy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'policy_year',
        'year_start_date',
        'year_end_date',
        'pro_rate_new_employees',
        'probation_period_months',
        'allow_negative_balance',
        'max_carry_over_days',
        'carry_over_expiry_date',
        'settings',
    ];

    protected $casts = [
        'year_start_date' => 'date',
        'year_end_date' => 'date',
        'carry_over_expiry_date' => 'date',
        'pro_rate_new_employees' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'settings' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class, 'policy_year', 'policy_year')
                    ->where('tenant_id', $this->tenant_id);
    }
}
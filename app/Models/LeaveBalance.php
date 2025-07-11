<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'leave_type_id',
        'policy_year',
        'entitled_days',
        'used_days',
        'pending_days',
        'available_days',
        'carried_over_days',
        'adjustment_days',
        'last_calculated_at',
        'calculation_details',
    ];

    protected $casts = [
        'entitled_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'pending_days' => 'decimal:2',
        'available_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
        'adjustment_days' => 'decimal:2',
        'last_calculated_at' => 'date',
        'calculation_details' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function transactions()
    {
        return $this->hasMany(LeaveTransaction::class);
    }

    /**
     * Calculate available days
     */
    public function calculateAvailableDays(): float
    {
        return $this->entitled_days + $this->carried_over_days + $this->adjustment_days - $this->used_days - $this->pending_days;
    }

    /**
     * Update available days
     */
    public function updateAvailableDays(): void
    {
        $this->available_days = $this->calculateAvailableDays();
        $this->save();
    }
}
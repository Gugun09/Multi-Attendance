<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'leave_id',
        'leave_balance_id',
        'transaction_type',
        'days',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'days' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leave()
    {
        return $this->belongsTo(Leave::class);
    }

    public function leaveBalance()
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
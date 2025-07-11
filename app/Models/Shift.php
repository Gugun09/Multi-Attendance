<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'start_time',
        'end_time',
        'working_days',
        'late_tolerance_minutes',
        'break_start_time',
        'break_end_time',
        'is_active',
        'description',
    ];

    protected $casts = [
        'working_days' => 'array',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'break_start_time' => 'datetime:H:i',
        'break_end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if today is a working day for this shift
     */
    public function isTodayWorkingDay(): bool
    {
        $today = strtolower(now()->format('l')); // monday, tuesday, etc.
        return in_array($today, $this->working_days);
    }

    /**
     * Check if given time is late
     */
    public function isLate(\Carbon\Carbon $checkInTime): bool
    {
        $shiftStart = \Carbon\Carbon::createFromFormat('H:i:s', $this->start_time);
        $tolerance = $shiftStart->addMinutes($this->late_tolerance_minutes);
        
        return $checkInTime->format('H:i:s') > $tolerance->format('H:i:s');
    }

    /**
     * Calculate late minutes
     */
    public function calculateLateMinutes(\Carbon\Carbon $checkInTime): int
    {
        if (!$this->isLate($checkInTime)) {
            return 0;
        }

        $shiftStart = \Carbon\Carbon::createFromFormat('H:i:s', $this->start_time);
        return $checkInTime->diffInMinutes($shiftStart);
    }
}
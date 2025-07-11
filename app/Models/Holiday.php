<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'date',
        'type',
        'description',
        'is_recurring',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if date is a holiday
     */
    public static function isHoliday($date, $tenantId = null): bool
    {
        $query = static::where('date', $date)
                      ->where('is_active', true);

        if ($tenantId) {
            $query->where(function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id'); // Include national holidays
            });
        } else {
            $query->whereNull('tenant_id'); // Only national holidays
        }

        return $query->exists();
    }

    /**
     * Get holidays in date range
     */
    public static function getHolidaysInRange($startDate, $endDate, $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::whereBetween('date', [$startDate, $endDate])
                      ->where('is_active', true)
                      ->orderBy('date');

        if ($tenantId) {
            $query->where(function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->get();
    }
}
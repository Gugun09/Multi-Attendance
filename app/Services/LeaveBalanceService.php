<?php

namespace App\Services;

use App\Models\User;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveTransaction;
use App\Models\Holiday;
use Carbon\Carbon;

class LeaveBalanceService
{
    /**
     * Initialize leave balances for a user for a specific year
     */
    public static function initializeUserBalances(User $user, string $policyYear): void
    {
        $policy = LeavePolicy::where('tenant_id', $user->tenant_id)
                            ->where('policy_year', $policyYear)
                            ->first();

        if (!$policy) {
            throw new \Exception("Leave policy not found for year {$policyYear}");
        }

        $leaveTypes = LeaveType::where('tenant_id', $user->tenant_id)
                              ->where('is_active', true)
                              ->get();

        foreach ($leaveTypes as $leaveType) {
            $balance = LeaveBalance::firstOrCreate([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'policy_year' => $policyYear,
            ]);

            if ($balance->wasRecentlyCreated) {
                static::calculateEntitlement($balance, $user, $leaveType, $policy);
            }
        }
    }

    /**
     * Calculate leave entitlement for a user
     */
    public static function calculateEntitlement(
        LeaveBalance $balance, 
        User $user, 
        LeaveType $leaveType, 
        LeavePolicy $policy
    ): void {
        $entitledDays = $leaveType->default_quota_days;

        // Pro-rate for new employees
        if ($policy->pro_rate_new_employees && $user->created_at) {
            $joinDate = Carbon::parse($user->created_at);
            $policyStart = $policy->year_start_date;
            $policyEnd = $policy->year_end_date;

            // If joined after policy start, pro-rate
            if ($joinDate->gt($policyStart)) {
                $totalDays = $policyStart->diffInDays($policyEnd) + 1;
                $remainingDays = $joinDate->diffInDays($policyEnd) + 1;
                $entitledDays = round(($remainingDays / $totalDays) * $entitledDays, 2);
            }
        }

        // Handle carry-over from previous year
        $carriedOverDays = static::calculateCarryOver($user, $leaveType, $policy);

        $balance->update([
            'entitled_days' => $entitledDays,
            'carried_over_days' => $carriedOverDays,
            'available_days' => $entitledDays + $carriedOverDays,
            'last_calculated_at' => now(),
            'calculation_details' => [
                'base_quota' => $leaveType->default_quota_days,
                'pro_rated' => $entitledDays,
                'carry_over' => $carriedOverDays,
                'calculated_at' => now()->toISOString(),
            ],
        ]);

        // Log the entitlement transaction
        LeaveTransaction::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'leave_balance_id' => $balance->id,
            'transaction_type' => 'credit',
            'days' => $entitledDays,
            'description' => "Annual entitlement for {$policy->policy_year}",
            'metadata' => [
                'type' => 'annual_entitlement',
                'policy_year' => $policy->policy_year,
            ],
            'created_by' => $user->id,
        ]);

        if ($carriedOverDays > 0) {
            LeaveTransaction::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'leave_balance_id' => $balance->id,
                'transaction_type' => 'credit',
                'days' => $carriedOverDays,
                'description' => "Carry-over from previous year",
                'metadata' => [
                    'type' => 'carry_over',
                    'from_year' => (string)((int)$policy->policy_year - 1),
                ],
                'created_by' => $user->id,
            ]);
        }
    }

    /**
     * Calculate carry-over days from previous year
     */
    public static function calculateCarryOver(User $user, LeaveType $leaveType, LeavePolicy $policy): float
    {
        $previousYear = (string)((int)$policy->policy_year - 1);
        
        $previousBalance = LeaveBalance::where('user_id', $user->id)
                                     ->where('leave_type_id', $leaveType->id)
                                     ->where('policy_year', $previousYear)
                                     ->first();

        if (!$previousBalance || !$leaveType->carry_over_rules['enabled']) {
            return 0;
        }

        $availableDays = $previousBalance->available_days;
        $maxCarryOver = min($leaveType->carry_over_rules['max_days'], $policy->max_carry_over_days);

        return min($availableDays, $maxCarryOver);
    }

    /**
     * Process leave request and update balance
     */
    public static function processLeaveRequest(\App\Models\Leave $leave): void
    {
        if ($leave->deducted_from_balance || $leave->status !== 'approved') {
            return;
        }

        $workingDays = static::calculateWorkingDays(
            $leave->start_date, 
            $leave->end_date, 
            $leave->user->tenant_id
        );

        // Find or create leave type
        $leaveType = LeaveType::where('tenant_id', $leave->tenant_id)
                             ->where('code', strtoupper($leave->type))
                             ->first();

        if (!$leaveType) {
            // Create default leave type if not exists
            $leaveType = LeaveType::create([
                'tenant_id' => $leave->tenant_id,
                'name' => ucfirst($leave->type) . ' Leave',
                'code' => strtoupper($leave->type),
                'default_quota_days' => 12,
                'requires_approval' => true,
                'is_paid' => true,
                'is_active' => true,
            ]);
        }

        $currentYear = $leave->start_date->year;
        $balance = LeaveBalance::where('user_id', $leave->user_id)
                              ->where('leave_type_id', $leaveType->id)
                              ->where('policy_year', $currentYear)
                              ->first();

        if (!$balance) {
            static::initializeUserBalances($leave->user, (string)$currentYear);
            $balance = LeaveBalance::where('user_id', $leave->user_id)
                                  ->where('leave_type_id', $leaveType->id)
                                  ->where('policy_year', $currentYear)
                                  ->first();
        }

        // Deduct from balance
        $balance->used_days += $workingDays;
        $balance->updateAvailableDays();
        $balance->save();

        // Update leave record
        $leave->update([
            'leave_type_id' => $leaveType->id,
            'calculated_days' => $workingDays,
            'deducted_from_balance' => true,
        ]);

        // Log transaction
        LeaveTransaction::create([
            'tenant_id' => $leave->tenant_id,
            'user_id' => $leave->user_id,
            'leave_id' => $leave->id,
            'leave_balance_id' => $balance->id,
            'transaction_type' => 'debit',
            'days' => $workingDays,
            'description' => "Leave taken: {$leave->type} ({$leave->start_date->format('M d')} - {$leave->end_date->format('M d, Y')})",
            'metadata' => [
                'leave_id' => $leave->id,
                'start_date' => $leave->start_date->toDateString(),
                'end_date' => $leave->end_date->toDateString(),
            ],
            'created_by' => $leave->approved_by ?? $leave->user_id,
        ]);
    }

    /**
     * Calculate working days excluding weekends and holidays
     */
    public static function calculateWorkingDays(Carbon $startDate, Carbon $endDate, int $tenantId): float
    {
        $workingDays = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Skip weekends (Saturday = 6, Sunday = 0)
            if (!in_array($current->dayOfWeek, [0, 6])) {
                // Check if it's not a holiday
                if (!Holiday::isHoliday($current->toDateString(), $tenantId)) {
                    $workingDays++;
                }
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Adjust leave balance manually
     */
    public static function adjustBalance(
        LeaveBalance $balance, 
        float $days, 
        string $reason, 
        User $adjustedBy
    ): void {
        $balance->adjustment_days += $days;
        $balance->updateAvailableDays();
        $balance->save();

        LeaveTransaction::create([
            'tenant_id' => $balance->tenant_id,
            'user_id' => $balance->user_id,
            'leave_balance_id' => $balance->id,
            'transaction_type' => $days > 0 ? 'credit' : 'debit',
            'days' => abs($days),
            'description' => "Manual adjustment: {$reason}",
            'metadata' => [
                'type' => 'manual_adjustment',
                'reason' => $reason,
                'adjusted_by' => $adjustedBy->name,
            ],
            'created_by' => $adjustedBy->id,
        ]);
    }

    /**
     * Get leave balance summary for user
     */
    public static function getBalanceSummary(User $user, string $policyYear): array
    {
        $balances = LeaveBalance::where('user_id', $user->id)
                               ->where('policy_year', $policyYear)
                               ->with('leaveType')
                               ->get();

        $summary = [];
        foreach ($balances as $balance) {
            $summary[] = [
                'leave_type' => $balance->leaveType->name,
                'code' => $balance->leaveType->code,
                'entitled' => $balance->entitled_days,
                'used' => $balance->used_days,
                'pending' => $balance->pending_days,
                'available' => $balance->available_days,
                'carried_over' => $balance->carried_over_days,
            ];
        }

        return $summary;
    }
}
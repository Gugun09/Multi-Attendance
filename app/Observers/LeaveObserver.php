<?php

namespace App\Observers;

use App\Models\Leave;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestRejected;

class LeaveObserver
{
    public function created(Leave $leave): void
    {
        // Notify admins when new leave request is submitted
        $admins = User::where('tenant_id', $leave->tenant_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new LeaveRequestSubmitted($leave));
        }
    }

    public function updated(Leave $leave): void
    {
        // Check if status was changed
        if ($leave->wasChanged('status')) {
            $originalStatus = $leave->getOriginal('status');
            $newStatus = $leave->status;

            // Only send notification if status changed from pending
            if ($originalStatus === 'pending') {
                if ($newStatus === 'approved') {
                    $leave->user->notify(new LeaveRequestApproved($leave));
                } elseif ($newStatus === 'rejected') {
                    $leave->user->notify(new LeaveRequestRejected($leave));
                }
            }
        }
    }
}
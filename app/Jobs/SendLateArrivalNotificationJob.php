<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\User;
use App\Notifications\LateArrivalAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLateArrivalNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Attendance $attendance
    ) {}

    public function handle(): void
    {
        // Get admins to notify
        $admins = User::where('tenant_id', $this->attendance->tenant_id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->get();

        // Send notifications
        foreach ($admins as $admin) {
            $admin->notify(new LateArrivalAlert($this->attendance));
        }
    }
}
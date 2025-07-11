<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Attendance $attendance
    ) {}

    public function handle(): void
    {
        // Process attendance calculations
        $this->calculateWorkHours();
        
        // Update related caches
        $this->invalidateRelatedCaches();
        
        // Send notifications if needed
        $this->sendNotifications();
    }

    private function calculateWorkHours(): void
    {
        if ($this->attendance->check_out_at && $this->attendance->check_in_at) {
            $workMinutes = $this->attendance->check_in_at->diffInMinutes($this->attendance->check_out_at);
            $actualWorkHours = gmdate('H:i:s', $workMinutes * 60);
            
            $this->attendance->update(['actual_work_hours' => $actualWorkHours]);
        }
    }

    private function invalidateRelatedCaches(): void
    {
        CacheService::invalidateAttendanceCache(
            $this->attendance->tenant_id,
            $this->attendance->user_id,
            $this->attendance->check_in_at->toDateString()
        );
    }

    private function sendNotifications(): void
    {
        // Send late arrival notifications
        if ($this->attendance->is_late && $this->attendance->late_minutes > 15) {
            // Dispatch notification job
            \App\Jobs\SendLateArrivalNotificationJob::dispatch($this->attendance);
        }
    }
}
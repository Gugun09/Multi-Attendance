<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GeofenceViolationAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Attendance $attendance
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Geofence Violation Alert 🚨')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('An employee has checked in from outside the allowed office area.')
            ->line('**Employee:** ' . $this->attendance->user->name)
            ->line('**Check-in Time:** ' . $this->attendance->check_in_at->format('M d, Y H:i:s'))
            ->line('**Distance from Office:** ' . $this->attendance->distance_from_office . ' meters')
            ->line('**Allowed Radius:** ' . $this->attendance->user->tenant->geofence_radius_meters . ' meters')
            ->when($this->attendance->check_in_location, function ($mail) {
                return $mail->line('**Location:** ' . $this->attendance->check_in_location);
            })
            ->action('View Attendance', url('/admin/attendances/' . $this->attendance->id . '/edit'))
            ->line('Please investigate this violation.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'attendance_id' => $this->attendance->id,
            'employee_name' => $this->attendance->user->name,
            'distance' => $this->attendance->distance_from_office,
            'allowed_radius' => $this->attendance->user->tenant->geofence_radius_meters,
            'message' => 'Geofence violation by ' . $this->attendance->user->name,
        ];
    }
}
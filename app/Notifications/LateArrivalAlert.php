<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateArrivalAlert extends Notification implements ShouldQueue
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
            ->subject('Late Arrival Alert ⏰')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('This is to inform you that ' . $this->attendance->user->name . ' arrived late today.')
            ->line('**Employee:** ' . $this->attendance->user->name)
            ->line('**Check-in Time:** ' . $this->attendance->check_in_at->format('M d, Y H:i:s'))
            ->line('**Late by:** ' . $this->attendance->late_minutes . ' minutes')
            ->when($this->attendance->check_in_location, function ($mail) {
                return $mail->line('**Location:** ' . $this->attendance->check_in_location);
            })
            ->action('View Attendance', url('/admin/attendances/' . $this->attendance->id . '/edit'))
            ->line('Please follow up if necessary.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'attendance_id' => $this->attendance->id,
            'employee_name' => $this->attendance->user->name,
            'check_in_time' => $this->attendance->check_in_at->format('Y-m-d H:i:s'),
            'late_minutes' => $this->attendance->late_minutes,
            'message' => $this->attendance->user->name . ' arrived ' . $this->attendance->late_minutes . ' minutes late',
        ];
    }
}
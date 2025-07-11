<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceSummary extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $summaryData,
        public string $period = 'daily'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = ucfirst($this->period) . ' Attendance Summary';
        $periodText = $this->period === 'daily' ? 'Today' : 'This Week';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Here is your ' . strtolower($periodText) . ' attendance summary:')
            ->line('**Total Employees:** ' . $this->summaryData['total_employees'])
            ->line('**Present:** ' . $this->summaryData['present'] . ' (' . $this->summaryData['present_percentage'] . '%)')
            ->line('**Late:** ' . $this->summaryData['late'] . ' (' . $this->summaryData['late_percentage'] . '%)')
            ->line('**Absent:** ' . $this->summaryData['absent'] . ' (' . $this->summaryData['absent_percentage'] . '%)')
            ->when($this->summaryData['top_late_employee'] ?? null, function ($mail) {
                return $mail->line('**Most Late Employee:** ' . $this->summaryData['top_late_employee'] . ' (' . $this->summaryData['top_late_minutes'] . ' minutes)');
            })
            ->action('View Dashboard', url('/admin'))
            ->line('Thank you for using our attendance system!')
            ->salutation('Best regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->summaryData;
    }
}
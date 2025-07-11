<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Leave $leave
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Leave Request Submitted')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new leave request has been submitted and requires your approval.')
            ->line('**Employee:** ' . $this->leave->user->name)
            ->line('**Type:** ' . ucfirst($this->leave->type))
            ->line('**Duration:** ' . $this->leave->start_date->format('M d, Y') . ' to ' . $this->leave->end_date->format('M d, Y'))
            ->line('**Days:** ' . $this->leave->start_date->diffInDays($this->leave->end_date) + 1 . ' day(s)')
            ->line('**Reason:** ' . $this->leave->reason)
            ->action('Review Request', url('/admin/leaves/' . $this->leave->id . '/edit'))
            ->line('Please review and approve/reject this request as soon as possible.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'leave_id' => $this->leave->id,
            'employee_name' => $this->leave->user->name,
            'leave_type' => $this->leave->type,
            'start_date' => $this->leave->start_date->format('Y-m-d'),
            'end_date' => $this->leave->end_date->format('Y-m-d'),
            'message' => 'New leave request from ' . $this->leave->user->name,
        ];
    }
}
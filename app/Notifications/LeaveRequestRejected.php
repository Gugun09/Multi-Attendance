<?php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestRejected extends Notification implements ShouldQueue
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
            ->subject('Leave Request Rejected ❌')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We regret to inform you that your leave request has been **rejected**.')
            ->line('**Type:** ' . ucfirst($this->leave->type))
            ->line('**Duration:** ' . $this->leave->start_date->format('M d, Y') . ' to ' . $this->leave->end_date->format('M d, Y'))
            ->line('**Days:** ' . $this->leave->start_date->diffInDays($this->leave->end_date) + 1 . ' day(s)')
            ->line('**Rejected by:** ' . $this->leave->approver->name)
            ->when($this->leave->admin_notes, function ($mail) {
                return $mail->line('**Reason for rejection:** ' . $this->leave->admin_notes);
            })
            ->action('View Details', url('/admin/leaves/' . $this->leave->id . '/edit'))
            ->line('Please contact your supervisor if you have any questions.')
            ->salutation('Best regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'leave_id' => $this->leave->id,
            'leave_type' => $this->leave->type,
            'start_date' => $this->leave->start_date->format('Y-m-d'),
            'end_date' => $this->leave->end_date->format('Y-m-d'),
            'rejected_by' => $this->leave->approver->name,
            'message' => 'Your ' . $this->leave->type . ' leave request has been rejected',
        ];
    }
}
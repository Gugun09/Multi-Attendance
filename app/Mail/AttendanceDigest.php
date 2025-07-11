<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $summaryData,
        public string $period = 'daily'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ucfirst($this->period) . ' Attendance Digest - ' . $this->summaryData['tenant_name'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.attendance.digest',
            with: [
                'summaryData' => $this->summaryData,
                'period' => $this->period,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
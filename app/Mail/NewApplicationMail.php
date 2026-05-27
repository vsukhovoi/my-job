<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewApplicationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Нова заявка: ' . $this->application->vacancy->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-application',
            with: [
                'employerName'   => $this->application->vacancy->company->user->name,
                'candidateName'  => $this->application->user->name,
                'vacancyTitle'   => $this->application->vacancy->title,
                'resumeUrl'      => $this->application->resume_url,
                'applicationUrl' => route('employer.candidate.detail', ['applicationId' => $this->application->id]),
            ],
        );
    }
}

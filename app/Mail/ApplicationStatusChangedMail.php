<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
        public readonly ApplicationStatus $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->newStatus) {
            ApplicationStatus::Interview => 'Вас запросили на співбесіду: ' . $this->application->vacancy->title,
            ApplicationStatus::Rejected  => 'Статус вашої заявки змінено: ' . $this->application->vacancy->title,
            default                      => 'Оновлення статусу заявки: ' . $this->application->vacancy->title,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.application-status-changed',
            with: [
                'candidateName' => $this->application->user->name,
                'vacancyTitle'  => $this->application->vacancy->title,
                'companyName'   => $this->application->vacancy->company?->name ?? '',
                'newStatus'     => $this->newStatus,
                'dashboardUrl'  => route('seeker.applications'),
            ],
        );
    }
}

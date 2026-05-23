<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Events\ApplicationStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ApplicationStatusChanged $event,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vacancy = $this->event->application->vacancy;

        [$subject, $line1, $line2] = match ($this->event->newStatus) {
            \App\Enums\ApplicationStatus::Interview => [
                "🎉 Вас запросили на співбесіду: {$vacancy->title}",
                "Чудові новини! Компанія **{$vacancy->company?->name}** запросила вас на співбесіду на вакансію **{$vacancy->title}**.",
                'Очікуйте контакту від роботодавця найближчим часом.',
            ],
            \App\Enums\ApplicationStatus::Rejected => [
                "Оновлення по вашій заявці: {$vacancy->title}",
                "На жаль, ваша заявка на вакансію **{$vacancy->title}** не пройшла далі розгляду.",
                'Не засмучуйтесь — продовжуйте подавати заявки на інші вакансії!',
            ],
            default => [
                "Статус заявки змінено: {$vacancy->title}",
                "Статус вашої заявки на вакансію **{$vacancy->title}** змінено на: **{$this->event->newStatus->label()}**",
                '',
            ],
        };

        $mail = (new MailMessage())
            ->subject($subject)
            ->line($line1);

        if ($line2) {
            $mail->line($line2);
        }

        return $mail->action('Переглянути мої заявки', route('seeker.applications'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id'  => $this->event->application->id,
            'old_status'       => $this->event->oldStatus?->value,
            'new_status'       => $this->event->newStatus->value,
            'changed_by_role'  => $this->event->changedBy->role->value,
        ];
    }
}

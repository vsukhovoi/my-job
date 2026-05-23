<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ApplicationStatus;
use App\Events\ApplicationStatusChanged;
use App\Mail\ApplicationStatusChangedMail;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Mail;

class NotifyApplicationStatusChanged
{
    public function __construct(
        private readonly TelegramNotifier $notifier,
    ) {}

    public function handle(ApplicationStatusChanged $event): void
    {
        $candidate = $event->application->user;
        $newStatus = $event->newStatus;

        if (! in_array($newStatus, [ApplicationStatus::Interview, ApplicationStatus::Rejected], true)) {
            return;
        }

        if ($candidate->prefersTelegram()) {
            $text = $this->resolveText($event);
            if ($text !== null) {
                $this->notifier->send((string) $candidate->telegram_id, $text);
            }
            return;
        }

        Mail::to($candidate->email)->queue(
            new ApplicationStatusChangedMail($event->application, $newStatus)
        );
    }

    private function resolveText(ApplicationStatusChanged $event): ?string
    {
        $vacancyTitle = e($event->application->vacancy->title);

        return match ($event->newStatus) {
            ApplicationStatus::Interview => sprintf(
                "🎉 Чудові новини! Роботодавець запросив вас на співбесіду на вакансію «%s». Очікуйте контакту найближчим часом.",
                $vacancyTitle,
            ),
            ApplicationStatus::Rejected => sprintf(
                "На жаль, ваша заявка на вакансію «%s» розглянута і не пройшла далі. Не засмучуйтесь — продовжуйте подавати на інші вакансії!",
                $vacancyTitle,
            ),
            default => null,
        };
    }
}

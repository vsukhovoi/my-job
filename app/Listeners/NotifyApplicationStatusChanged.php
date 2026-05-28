<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ApplicationStatus;
use App\Events\ApplicationStatusChanged;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyApplicationStatusChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly TelegramNotifier $notifier,
    ) {}

    public function handle(ApplicationStatusChanged $event): void
    {
        $candidate = $event->application->user;

        if (! $candidate->prefersTelegram()) {
            return;
        }

        $text = $this->resolveText($event);

        if ($text === null) {
            return;
        }

        $this->notifier->send((string) $candidate->telegram_id, $text);
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

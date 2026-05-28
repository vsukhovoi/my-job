<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ApplicationStatusChangedMail;
use App\Models\Application;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

final class SendApplicationStatusNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $applicationId,
    ) {}

    public function handle(TelegramService $service): void
    {
        $application = Application::with(['vacancy.company', 'user'])->find($this->applicationId);

        if (! $application) {
            return;
        }

        $candidate = $application->user;

        if ($candidate->prefersEmail()) {
            Mail::to($candidate->email)->send(new ApplicationStatusChangedMail($application, $application->status));
        }

        if (! $candidate->prefersTelegram()) {
            return;
        }

        $vacancy = $application->vacancy;
        $status  = $application->status->label();

        $text = "📋 <b>Статус вашої заявки змінено</b>\n\n"
            . "Вакансія: <b>{$vacancy->title}</b>\n"
            . "Компанія: <b>{$vacancy->company->name}</b>\n"
            . "Новий статус: <b>{$status}</b>\n\n"
            . "<a href=\"" . rtrim(config('app.url'), '/') . "/jobs/{$vacancy->slug}\">Переглянути вакансію</a>";

        $service->sendMessage((int) $candidate->telegram_id, $text);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InterviewResponseSubmitted;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyInterviewResponseSubmitted implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly TelegramNotifier $notifier) {}

    public function handle(InterviewResponseSubmitted $event): void
    {
        $response = $event->interviewResponse;
        $response->loadMissing('interviewRequest.application.vacancy.company');

        $employer = $response->interviewRequest?->employer;

        if (! $employer || ! $employer->prefersTelegram()) {
            return;
        }

        $candidateName = $response->candidate?->name ?? 'Кандидат';
        $vacancyTitle  = $response->interviewRequest->application->vacancy->title ?? '';
        $viewUrl       = route('interview.view', $response->id);

        $text = "✅ <b>Кандидат відповів на співбесіду</b>\n\n"
            . "<b>{$candidateName}</b> надіслав(ла) відповіді на питання асинхронної співбесіди"
            . " для вакансії «{$vacancyTitle}».";

        $keyboard = [
            [['text' => '👀 Переглянути відповіді', 'url' => $viewUrl]],
        ];

        try {
            $this->notifier->sendMessageWithKeyboard(
                telegramId: (int) $employer->telegram_id,
                text: $text,
                inlineKeyboard: $keyboard,
            );
        } catch (\Throwable $e) {
            Log::error('NotifyInterviewResponseSubmitted: failed', [
                'response_id' => $response->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}

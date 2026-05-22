<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InterviewRequestSent;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyInterviewRequestSent implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly TelegramNotifier $notifier) {}

    public function handle(InterviewRequestSent $event): void
    {
        $request   = $event->interviewRequest;
        $candidate = $request->application?->user;

        if (! $candidate || ! $candidate->prefersTelegram()) {
            return;
        }

        $request->loadMissing('application.vacancy.company');

        $companyName   = $request->application->vacancy->company->name ?? '';
        $vacancyTitle  = $request->application->vacancy->title ?? '';
        $expiresAt     = $request->deadline_at?->locale('uk')->isoFormat('D MMMM, HH:mm') ?? '—';
        $questionsCount = count($request->questions ?? []);
        $respondUrl    = route('interview.respond', $request->id);

        $text = "📨 <b>Запит на співбесіду</b>\n\n"
            . "Компанія <b>{$companyName}</b> запрошує вас пройти асинхронну співбесіду"
            . " на вакансію «{$vacancyTitle}».\n\n"
            . "⏰ Термін відповіді: до <b>{$expiresAt}</b>\n"
            . "📝 Кількість питань: <b>{$questionsCount}</b>";

        $keyboard = [
            [['text' => '📝 Відповісти на питання', 'url' => $respondUrl]],
        ];

        try {
            $this->notifier->sendMessageWithKeyboard(
                telegramId: (int) $candidate->telegram_id,
                text: $text,
                inlineKeyboard: $keyboard,
            );
        } catch (\Throwable $e) {
            Log::error('NotifyInterviewRequestSent: failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}

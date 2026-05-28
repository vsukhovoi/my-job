<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\NewApplicationMail;
use App\Models\Application;
use App\Services\Telegram\CallbackDataSigner;
use App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

final class SendNewApplicationNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $applicationId,
    ) {}

    public function handle(TelegramNotifier $notifier, CallbackDataSigner $signer, ApplicationUrlGenerator $urls): void
    {
        $application = Application::with(['vacancy.company.user', 'user'])
            ->find($this->applicationId);

        if (! $application) {
            return;
        }

        $employer = $application->vacancy->company->user;

        if ($employer->prefersTelegram()) {
            $notifier->sendMessageWithKeyboard(
                telegramId:     (int) $employer->telegram_id,
                text:           $this->formatMessage($application),
                inlineKeyboard: $this->buildKeyboard($application, $signer, $urls),
            );
        } elseif ($employer->prefersEmail()) {
            Mail::to($employer->email)->send(new NewApplicationMail($application));
        }
    }

    private function formatMessage(Application $application): string
    {
        return sprintf(
            "📨 <b>Нова заявка на вашу вакансію</b>\n\nКандидат: %s\nВакансія: %s\nРезюме: %s",
            e($application->user->name),
            e($application->vacancy->title),
            $application->resume_url ?: 'не додано',
        );
    }

    /**
     * @return array<array<array{text: string, url?: string, callback_data?: string}>>
     */
    private function buildKeyboard(Application $application, CallbackDataSigner $signer, ApplicationUrlGenerator $urls): array
    {
        return [
            [
                ['text' => '📄 Переглянути CV',          'url'           => $urls->cvViewUrl($application)],
                ['text' => '✅ Запросити на співбесіду', 'callback_data' => $signer->sign('invite', 'application', $application->id)],
            ],
            [
                ['text' => '❌ Відхилити', 'callback_data' => $signer->sign('reject', 'application', $application->id)],
                ['text' => '💬 Написати', 'url'           => $urls->chatUrl($application)],
            ],
        ];
    }
}

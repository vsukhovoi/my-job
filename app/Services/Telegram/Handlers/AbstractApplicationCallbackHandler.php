<?php

declare(strict_types=1);

namespace App\Services\Telegram\Handlers;

use App\Contracts\TelegramCallbackHandlerInterface;
use App\Models\Application;
use App\Models\User;
use App\Services\TelegramNotifier;

abstract class AbstractApplicationCallbackHandler implements TelegramCallbackHandlerInterface
{
    public function __construct(
        protected readonly TelegramNotifier $notifier,
    ) {}

    protected function getApplication(int $applicationId): ?Application
    {
        return Application::with(['vacancy.company', 'user'])->find($applicationId);
    }

    protected function ensureEmployerOwnsApplication(Application $application, User $employer): bool
    {
        return $application->vacancy->company->user_id === $employer->id;
    }

    protected function clearKeyboardWithStatus(int $chatId, int $messageId, Application $application, string $statusLine): void
    {
        $text = $this->reconstructMessageText($application) . "\n\n" . $statusLine;
        $this->notifier->editMessageText($chatId, $messageId, $text, []);
    }

    private function reconstructMessageText(Application $application): string
    {
        return sprintf(
            "📨 <b>Нова заявка на вашу вакансію</b>\n\nКандидат: %s\nВакансія: %s\nРезюме: %s",
            e($application->user->name),
            e($application->vacancy->title),
            $application->resume_url ?: 'не додано',
        );
    }
}

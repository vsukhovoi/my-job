<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelegramSubscription;
use App\Models\Vacancy;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class SendVacancyAlerts extends Command
{
    protected $signature   = 'app:send-vacancy-alerts';
    protected $description = 'Send Telegram alerts for new vacancies to subscribed users';

    public function handle(TelegramNotifier $notifier): int
    {
        $since = now()->subHour();

        $vacancies = Vacancy::with(['company', 'category'])
            ->where('is_active', true)
            ->where('published_at', '>=', $since)
            ->get();

        if ($vacancies->isEmpty()) {
            $this->info('No new vacancies in the last hour.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($vacancies as $vacancy) {
            $cacheKey = "vacancy_alert_sent:{$vacancy->id}";

            if (Cache::has($cacheKey)) {
                continue;
            }

            $subscribers = TelegramSubscription::where('category_id', $vacancy->category_id)
                ->pluck('telegram_id');

            if ($subscribers->isEmpty()) {
                Cache::put($cacheKey, true, now()->addHours(25));
                continue;
            }

            $salary = $vacancy->salary_from
                ? "\n💰 " . number_format((int) $vacancy->salary_from) . '–' . number_format((int) $vacancy->salary_to) . " {$vacancy->currency}"
                : '';

            $location = $vacancy->company->location
                ? " · {$vacancy->company->location}"
                : '';

            $text = "🆕 <b>Нова вакансія у категорії {$vacancy->category->name}</b>\n\n"
                . "📌 <b>{$vacancy->title}</b>\n"
                . "🏭 {$vacancy->company->name}{$location}"
                . $salary
                . "\n\n<a href=\"" . rtrim(config('app.url'), '/') . "/jobs/{$vacancy->slug}\">👉 Переглянути вакансію</a>";

            foreach ($subscribers as $telegramId) {
                $notifier->send((string) $telegramId, $text);
                $sent++;
            }

            Cache::put($cacheKey, true, now()->addHours(25));
        }

        $this->info("Sent {$sent} alert(s) for {$vacancies->count()} new vacancies.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Vacancy;
use App\Notifications\VacancyExpiringSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyExpiringVacanciesCommand extends Command
{
    protected $signature = 'app:notify-expiring-vacancies
                            {--hours=24 : За скільки годин до експайру повідомляти}
                            {--dry-run : Не відправляти, лише вивести список}
                            {--limit=100 : Максимум вакансій за один запуск}';

    protected $description = 'Надсилає роботодавцям сповіщення про вакансії, що скоро завершаться';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $hours     = (int) $this->option('hours');
        $isDryRun  = (bool) $this->option('dry-run');
        $limit     = (int) $this->option('limit');

        $vacancies = Vacancy::pendingExpiryNotification($hours)
            ->with('company.user')
            ->limit($limit)
            ->get();

        if ($vacancies->isEmpty()) {
            $this->info('Немає вакансій, що потребують сповіщення.');
            return self::SUCCESS;
        }

        $sent = $skipped = $failed = 0;

        foreach ($vacancies as $vacancy) {
            $user = $vacancy->company?->user;

            if (! $user) {
                Log::channel('vacancies')->warning('Notify-expiring: vacancy without user', ['vacancy_id' => $vacancy->id]);
                $skipped++;
                continue;
            }

            if (! $user->prefersTelegram() && ! $user->prefersEmail()) {
                Log::channel('vacancies')->info('Notify-expiring: no notification channel', [
                    'vacancy_id' => $vacancy->id,
                    'user_id'    => $user->id,
                ]);
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $channel = $user->prefersTelegram() ? 'telegram' : 'email';
                $this->line("[dry-run] Vacancy #{$vacancy->id} «{$vacancy->title}» → user #{$user->id} ({$channel})");
                $sent++;
                continue;
            }

            try {
                $user->notify(new VacancyExpiringSoonNotification($vacancy));
                $vacancy->markExpiryNotificationSent();

                Log::channel('vacancies')->info('Notify-expiring: sent', [
                    'vacancy_id'  => $vacancy->id,
                    'user_id'     => $user->id,
                    'telegram_id' => $user->telegram_id,
                ]);
                $sent++;

                usleep(50_000);
            } catch (\Throwable $e) {
                Log::channel('vacancies')->error('Notify-expiring: failed', [
                    'vacancy_id' => $vacancy->id,
                    'error'      => $e->getMessage(),
                ]);
                report($e);
                $failed++;
            }
        }

        $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);
        $this->info("Готово за {$elapsedMs} мс. Надіслано: {$sent}, пропущено: {$skipped}, помилки: {$failed}.");

        return self::SUCCESS;
    }
}

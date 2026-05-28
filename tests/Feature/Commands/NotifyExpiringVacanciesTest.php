<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\VacancyExpiringSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotifyExpiringVacanciesTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpiringVacancy(array $employerAttrs = [], int $hoursLeft = 23): array
    {
        $employer = User::factory()->employer()->create($employerAttrs);
        $company  = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy  = Vacancy::factory()->create([
            'company_id'                  => $company->id,
            'is_active'                   => true,
            'published_at'                => now()->subDays(7),
            'expires_at'                  => now()->addHours($hoursLeft),
            'expiry_notification_sent_at' => null,
            'status'                      => \App\Enums\VacancyStatus::Active,
        ]);

        return [$vacancy, $employer];
    }

    // ── 8.2 dry-run ──────────────────────────────────────────────────────────

    #[Test]
    public function dry_run_outputs_vacancy_list_without_sending(): void
    {
        [$vacancy, $employer] = $this->makeExpiringVacancy([
            'notify_via_telegram' => true,
            'telegram_id'         => 123456789,
        ]);

        Notification::fake();

        $this->artisan('app:notify-expiring-vacancies', ['--hours' => 24, '--dry-run' => true])
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($vacancy->fresh()->expiry_notification_sent_at);
    }

    // ── 8.3 реальне надсилання ───────────────────────────────────────────────

    #[Test]
    public function it_sends_telegram_notification_to_employer_with_telegram(): void
    {
        [$vacancy, $employer] = $this->makeExpiringVacancy([
            'notify_via_telegram' => true,
            'notify_via_email'    => false,
            'telegram_id'         => 123456789,
        ]);

        Notification::fake();

        $this->artisan('app:notify-expiring-vacancies', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutputToContain('Надіслано: 1');

        Notification::assertSentTo($employer, VacancyExpiringSoonNotification::class,
            fn ($n) => $n->vacancy->id === $vacancy->id
        );

        $this->assertNotNull($vacancy->fresh()->expiry_notification_sent_at);
    }

    #[Test]
    public function it_sends_email_notification_to_employer_without_telegram(): void
    {
        [$vacancy, $employer] = $this->makeExpiringVacancy([
            'notify_via_email'    => true,
            'notify_via_telegram' => false,
            'telegram_id'         => null,
        ]);

        Notification::fake();

        $this->artisan('app:notify-expiring-vacancies', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutputToContain('Надіслано: 1');

        Notification::assertSentTo($employer, VacancyExpiringSoonNotification::class);
        $this->assertNotNull($vacancy->fresh()->expiry_notification_sent_at);
    }

    #[Test]
    public function it_skips_employer_with_no_notification_channel(): void
    {
        [$vacancy, $employer] = $this->makeExpiringVacancy([
            'notify_via_email'    => false,
            'notify_via_telegram' => false,
            'telegram_id'         => null,
        ]);

        Notification::fake();

        $this->artisan('app:notify-expiring-vacancies', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutputToContain('пропущено: 1');

        Notification::assertNothingSent();
        $this->assertNull($vacancy->fresh()->expiry_notification_sent_at);
    }

    #[Test]
    public function it_does_not_resend_if_already_notified(): void
    {
        [$vacancy, $employer] = $this->makeExpiringVacancy([
            'notify_via_telegram' => true,
            'telegram_id'         => 123456789,
        ]);

        $vacancy->update(['expiry_notification_sent_at' => now()->subHour()]);

        Notification::fake();

        $this->artisan('app:notify-expiring-vacancies', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutputToContain('Немає вакансій');

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_does_not_notify_for_vacancy_expiring_outside_window(): void
    {
        [$vacancy, $employer] = $this->makeExpiringVacancy([
            'notify_via_telegram' => true,
            'telegram_id'         => 123456789,
        ], hoursLeft: 48);

        Notification::fake();

        $this->artisan('app:notify-expiring-vacancies', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutputToContain('Немає вакансій');

        Notification::assertNothingSent();
    }
}

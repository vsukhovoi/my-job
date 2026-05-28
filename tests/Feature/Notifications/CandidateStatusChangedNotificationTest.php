<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Events\ApplicationStatusChanged;
use App\Listeners\NotifyApplicationStatusChanged;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CandidateStatusChangedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $botUrl;
    private NotifyApplicationStatusChanged $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl   = config('services.telegram_bot.api_url', 'http://localhost:8080');
        $this->listener = app(NotifyApplicationStatusChanged::class);
    }

    private function makeApplicationWithTelegramCandidate(): array
    {
        $employer  = User::factory()->employer()->create();
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy   = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create([
            'role'                => UserRole::Candidate,
            'telegram_id'         => 444444444,
            'notify_via_telegram' => true,
            'notify_via_email'    => false,
        ]);
        $application = Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
        ]);

        return [$employer, $candidate, $application];
    }

    #[Test]
    public function it_sends_correct_text_to_candidate_when_invited_to_interview(): void
    {
        [$employer, $candidate, $application] = $this->makeApplicationWithTelegramCandidate();

        Http::fake(["{$this->botUrl}/send-message" => Http::response(['success' => true], 200)]);

        $event = new ApplicationStatusChanged(
            application: $application->load(['vacancy', 'user']),
            oldStatus:   ApplicationStatus::Pending,
            newStatus:   ApplicationStatus::Interview,
            changedBy:   $employer,
        );

        $this->listener->handle($event);

        Http::assertSent(function ($request) use ($application) {
            $text = $request->data()['text'];
            $this->assertStringContainsString('співбесіду', $text);
            $this->assertStringContainsString($application->vacancy->title, $text);
            $this->assertStringNotContainsString('посередництво', $text);
            return true;
        });
    }

    #[Test]
    public function it_sends_correct_text_to_candidate_when_rejected(): void
    {
        [$employer, $candidate, $application] = $this->makeApplicationWithTelegramCandidate();

        Http::fake(["{$this->botUrl}/send-message" => Http::response(['success' => true], 200)]);

        $event = new ApplicationStatusChanged(
            application: $application->load(['vacancy', 'user']),
            oldStatus:   ApplicationStatus::Pending,
            newStatus:   ApplicationStatus::Rejected,
            changedBy:   $employer,
        );

        $this->listener->handle($event);

        Http::assertSent(function ($request) use ($application) {
            $text = $request->data()['text'];
            $this->assertStringContainsString($application->vacancy->title, $text);
            $this->assertStringContainsString('не пройшла', $text);
            $this->assertStringNotContainsString('посередництво', $text);
            return true;
        });
    }
}

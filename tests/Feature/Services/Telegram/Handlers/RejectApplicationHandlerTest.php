<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Telegram\Handlers;

use App\DataTransferObjects\CallbackDataPayload;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Events\ApplicationStatusChanged;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Telegram\Handlers\RejectApplicationHandler;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RejectApplicationHandlerTest extends TestCase
{
    use RefreshDatabase;

    private string $botUrl;
    private RejectApplicationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl  = config('services.telegram_bot.api_url', 'http://localhost:8080');
        $this->handler = app(RejectApplicationHandler::class);
    }

    private function makeEmployerWithApplication(ApplicationStatus $status = ApplicationStatus::Pending): array
    {
        $employer  = User::factory()->employer()->create([
            'telegram_id'         => 111111111,
            'notify_via_telegram' => true,
            'notify_via_email'    => false,
        ]);
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy   = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['role' => UserRole::Candidate]);
        $application = Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
            'status'     => $status,
        ]);

        return [$employer, $application];
    }

    private function payload(int $applicationId): CallbackDataPayload
    {
        return new CallbackDataPayload('reject', 'application', $applicationId);
    }

    #[Test]
    public function it_can_handle_reject_application_callback(): void
    {
        $payload = $this->payload(1);
        $this->assertTrue($this->handler->canHandle($payload));
    }

    #[Test]
    public function it_updates_application_status_to_rejected(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake(["{$this->botUrl}/edit-message" => Http::response(['ok' => true], 200)]);
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $employer, messageId: 10);

        $this->assertEquals(ApplicationStatus::Rejected, $application->fresh()->status);
    }

    #[Test]
    public function it_does_not_act_when_employer_does_not_own_application(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();
        $otherEmployer = User::factory()->employer()->create(['telegram_id' => 999999999]);

        Http::fake();
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $otherEmployer, messageId: 10);

        $this->assertNotEquals(ApplicationStatus::Rejected, $application->fresh()->status);
        Http::assertNothingSent();
        Event::assertNotDispatched(ApplicationStatusChanged::class);
    }

    #[Test]
    public function it_is_idempotent_when_application_already_rejected(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication(ApplicationStatus::Rejected);

        Http::fake();
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $employer, messageId: 10);

        Http::assertNothingSent();
        Event::assertNotDispatched(ApplicationStatusChanged::class);
    }

    #[Test]
    public function it_dispatches_application_status_changed_event_for_candidate_notification(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake(["{$this->botUrl}/edit-message" => Http::response(['ok' => true], 200)]);
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $employer, messageId: 10);

        Event::assertDispatched(ApplicationStatusChanged::class, function ($event) {
            return $event->newStatus === ApplicationStatus::Rejected;
        });
    }
}

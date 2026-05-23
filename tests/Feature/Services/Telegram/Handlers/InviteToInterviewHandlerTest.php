<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Telegram\Handlers;

use App\DataTransferObjects\CallbackDataPayload;
use App\Enums\ApplicationStatus;
use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Events\ApplicationStatusChanged;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Telegram\Handlers\InviteToInterviewHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InviteToInterviewHandlerTest extends TestCase
{
    use RefreshDatabase;

    private string $botUrl;
    private InviteToInterviewHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl  = config('services.telegram_bot.api_url', 'http://localhost:8080');
        $this->handler = app(InviteToInterviewHandler::class);
    }

    private function makeEmployerWithApplication(ApplicationStatus $status = ApplicationStatus::Pending): array
    {
        $employer  = User::factory()->employer()->create([
            'telegram_id'          => 222222222,
            'notification_channel' => NotificationChannel::Telegram,
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
        return new CallbackDataPayload('invite', 'application', $applicationId);
    }

    #[Test]
    public function it_can_handle_invite_application_callback(): void
    {
        $payload = $this->payload(1);
        $this->assertTrue($this->handler->canHandle($payload));
    }

    #[Test]
    public function it_updates_application_status_to_interview(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake(["{$this->botUrl}/edit-message" => Http::response(['ok' => true], 200)]);
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $employer, messageId: 20);

        $this->assertEquals(ApplicationStatus::Interview, $application->fresh()->status);
    }

    #[Test]
    public function it_does_not_act_when_employer_does_not_own_application(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();
        $otherEmployer = User::factory()->employer()->create(['telegram_id' => 888888888]);

        Http::fake();
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $otherEmployer, messageId: 20);

        $this->assertNotEquals(ApplicationStatus::Interview, $application->fresh()->status);
        Http::assertNothingSent();
        Event::assertNotDispatched(ApplicationStatusChanged::class);
    }

    #[Test]
    public function it_is_idempotent_when_application_already_invited(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication(ApplicationStatus::Interview);

        Http::fake();
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $employer, messageId: 20);

        Http::assertNothingSent();
        Event::assertNotDispatched(ApplicationStatusChanged::class);
    }

    #[Test]
    public function it_dispatches_application_status_changed_event_for_candidate_notification(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake(["{$this->botUrl}/edit-message" => Http::response(['ok' => true], 200)]);
        Event::fake([ApplicationStatusChanged::class]);

        $this->handler->handle($this->payload($application->id), $employer, messageId: 20);

        Event::assertDispatched(ApplicationStatusChanged::class, function ($event) {
            return $event->newStatus === ApplicationStatus::Interview;
        });
    }
}

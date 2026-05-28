<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\InterviewRequestStatus;
use App\Enums\UserRole;
use App\Events\InterviewRequestSent;
use App\Events\InterviewResponseSubmitted;
use App\Listeners\NotifyInterviewRequestSent;
use App\Listeners\NotifyInterviewResponseSubmitted;
use App\Models\Application;
use App\Models\Company;
use App\Models\InterviewRequest;
use App\Models\InterviewResponse;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterviewNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeInterviewRequest(array $candidateAttrs = [], array $employerAttrs = []): InterviewRequest
    {
        $employer  = User::factory()->employer()->create($employerAttrs);
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy   = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(array_merge(['role' => UserRole::Candidate], $candidateAttrs));
        $application = Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
        ]);

        return InterviewRequest::create([
            'application_id'   => $application->id,
            'employer_user_id' => $employer->id,
            'questions'        => ['Розкажіть про себе?', 'Який ваш досвід?'],
            'deadline_at'      => now()->addDays(3),
            'status'           => InterviewRequestStatus::Pending,
        ]);
    }

    #[Test]
    public function it_sends_telegram_notification_when_interview_request_sent_and_candidate_prefers_telegram(): void
    {
        $request = $this->makeInterviewRequest([
            'notify_via_telegram' => true, 'notify_via_email' => false,
            'telegram_id'          => 111222333,
        ]);

        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldReceive('sendMessageWithKeyboard')
            ->once()
            ->with(111222333, Mockery::type('string'), Mockery::type('array'));

        $this->instance(TelegramNotifier::class, $mock);

        app(NotifyInterviewRequestSent::class)->handle(new InterviewRequestSent($request));
    }

    #[Test]
    public function it_skips_telegram_when_candidate_has_no_telegram_id(): void
    {
        $request = $this->makeInterviewRequest([
            'notify_via_telegram' => true, 'notify_via_email' => false,
            'telegram_id'          => null,
        ]);

        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldNotReceive('sendMessageWithKeyboard');

        $this->instance(TelegramNotifier::class, $mock);

        app(NotifyInterviewRequestSent::class)->handle(new InterviewRequestSent($request));
    }

    #[Test]
    public function it_skips_telegram_when_candidate_prefers_email(): void
    {
        $request = $this->makeInterviewRequest([
            'notify_via_email' => true, 'notify_via_telegram' => false,
            'telegram_id'          => 111222333,
        ]);

        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldNotReceive('sendMessageWithKeyboard');

        $this->instance(TelegramNotifier::class, $mock);

        app(NotifyInterviewRequestSent::class)->handle(new InterviewRequestSent($request));
    }

    #[Test]
    public function it_sends_telegram_notification_when_interview_response_submitted_and_employer_prefers_telegram(): void
    {
        $request = $this->makeInterviewRequest([], [
            'notify_via_telegram' => true, 'notify_via_email' => false,
            'telegram_id'          => 444555666,
        ]);

        $response = InterviewResponse::create([
            'interview_request_id' => $request->id,
            'user_id'              => $request->application->user_id,
            'answers'              => [['question_index' => 0, 'text' => 'Відповідь 1']],
            'submitted_at'         => now(),
        ]);

        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldReceive('sendMessageWithKeyboard')
            ->once()
            ->with(444555666, Mockery::type('string'), Mockery::type('array'));

        $this->instance(TelegramNotifier::class, $mock);

        app(NotifyInterviewResponseSubmitted::class)->handle(new InterviewResponseSubmitted($response));
    }

    #[Test]
    public function it_skips_telegram_notification_when_employer_has_no_telegram_id(): void
    {
        $request = $this->makeInterviewRequest([], [
            'notify_via_telegram' => true, 'notify_via_email' => false,
            'telegram_id'          => null,
        ]);

        $response = InterviewResponse::create([
            'interview_request_id' => $request->id,
            'user_id'              => $request->application->user_id,
            'answers'              => [],
            'submitted_at'         => now(),
        ]);

        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldNotReceive('sendMessageWithKeyboard');

        $this->instance(TelegramNotifier::class, $mock);

        app(NotifyInterviewResponseSubmitted::class)->handle(new InterviewResponseSubmitted($response));
    }

    #[Test]
    public function it_includes_deep_link_in_notification_payload(): void
    {
        $request = $this->makeInterviewRequest([
            'notify_via_telegram' => true, 'notify_via_email' => false,
            'telegram_id'          => 777888999,
        ]);

        $capturedKeyboard = null;

        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldReceive('sendMessageWithKeyboard')
            ->once()
            ->withArgs(function (int $id, string $text, array $keyboard) use (&$capturedKeyboard) {
                $capturedKeyboard = $keyboard;
                return true;
            });

        $this->instance(TelegramNotifier::class, $mock);

        app(NotifyInterviewRequestSent::class)->handle(new InterviewRequestSent($request));

        $this->assertNotNull($capturedKeyboard);
        $this->assertNotEmpty($capturedKeyboard[0]);
        $this->assertStringContainsString('/interview-request/', $capturedKeyboard[0][0]['url']);
        $this->assertStringContainsString('respond', $capturedKeyboard[0][0]['url']);
    }
}

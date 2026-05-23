<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Jobs\SendNewApplicationNotification;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewApplicationNotificationWithKeyboardTest extends TestCase
{
    use RefreshDatabase;

    private string $botUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl = config('services.telegram_bot.api_url', 'http://localhost:8080');
    }

    private function makeApplicationForTelegramEmployer(): Application
    {
        $employer  = User::factory()->employer()->create([
            'telegram_id'          => 123456789,
            'notification_channel' => NotificationChannel::Telegram,
        ]);
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy   = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['role' => UserRole::Candidate]);

        return Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
            'resume_url' => 'https://example.com/cv.pdf',
        ]);
    }

    #[Test]
    public function it_sends_message_with_4_buttons_when_employer_prefers_telegram(): void
    {
        $application = $this->makeApplicationForTelegramEmployer();

        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response(['success' => true, 'message_id' => 1], 200),
        ]);

        (new SendNewApplicationNotification($application->id))->handle(
            app(\App\Services\TelegramNotifier::class),
            app(\App\Services\Telegram\CallbackDataSigner::class),
            app(\App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator::class),
        );

        Http::assertSent(function ($request) {
            $body = $request->data();
            $keyboard = $body['inline_keyboard'];

            $this->assertCount(2, $keyboard);
            $this->assertCount(2, $keyboard[0]);
            $this->assertCount(2, $keyboard[1]);

            $hasUrl           = fn ($btn) => isset($btn['url']);
            $hasCallbackData  = fn ($btn) => isset($btn['callback_data']);

            $this->assertTrue($hasUrl($keyboard[0][0]),          '📄 CV button must have url');
            $this->assertTrue($hasCallbackData($keyboard[0][1]), '✅ Invite button must have callback_data');
            $this->assertTrue($hasCallbackData($keyboard[1][0]), '❌ Reject button must have callback_data');
            $this->assertTrue($hasUrl($keyboard[1][1]),          '💬 Chat button must have url');

            return true;
        });
    }

    #[Test]
    public function it_falls_back_when_employer_has_no_telegram_id(): void
    {
        $employer  = User::factory()->employer()->create([
            'telegram_id'          => null,
            'notification_channel' => NotificationChannel::Email,
        ]);
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy   = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['role' => UserRole::Candidate]);
        $application = Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
        ]);

        Http::fake();

        (new SendNewApplicationNotification($application->id))->handle(
            app(\App\Services\TelegramNotifier::class),
            app(\App\Services\Telegram\CallbackDataSigner::class),
            app(\App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator::class),
        );

        Http::assertNothingSent();
    }

    #[Test]
    public function it_passes_chat_id_as_int_to_notifier(): void
    {
        $application = $this->makeApplicationForTelegramEmployer();

        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response(['success' => true, 'message_id' => 1], 200),
        ]);

        (new SendNewApplicationNotification($application->id))->handle(
            app(\App\Services\TelegramNotifier::class),
            app(\App\Services\Telegram\CallbackDataSigner::class),
            app(\App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator::class),
        );

        Http::assertSent(function ($request) {
            $this->assertTrue(is_int($request->data()['chat_id']), 'chat_id must be int');
            return true;
        });
    }

    #[Test]
    public function it_uses_bearer_authorization_header_when_calling_python_bot(): void
    {
        $application = $this->makeApplicationForTelegramEmployer();

        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response(['success' => true, 'message_id' => 1], 200),
        ]);

        (new SendNewApplicationNotification($application->id))->handle(
            app(\App\Services\TelegramNotifier::class),
            app(\App\Services\Telegram\CallbackDataSigner::class),
            app(\App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator::class),
        );

        Http::assertSent(function ($request) {
            $this->assertStringStartsWith('Bearer', $request->header('Authorization')[0] ?? '');
            return true;
        });
    }

    #[Test]
    public function it_includes_candidate_name_and_vacancy_title_in_message_text(): void
    {
        $application = $this->makeApplicationForTelegramEmployer();

        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response(['success' => true, 'message_id' => 1], 200),
        ]);

        (new SendNewApplicationNotification($application->id))->handle(
            app(\App\Services\TelegramNotifier::class),
            app(\App\Services\Telegram\CallbackDataSigner::class),
            app(\App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator::class),
        );

        Http::assertSent(function ($request) use ($application) {
            $text = $request->data()['text'];
            $this->assertStringContainsString($application->user->name, $text);
            $this->assertStringContainsString($application->vacancy->title, $text);
            return true;
        });
    }
}

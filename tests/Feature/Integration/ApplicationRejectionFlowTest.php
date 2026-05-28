<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Telegram\CallbackDataSigner;
use App\Services\Telegram\TelegramCallbackRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationRejectionFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $botUrl;
    private CallbackDataSigner $signer;
    private TelegramCallbackRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl = config('services.telegram_bot.api_url', 'http://localhost:8080');
        $this->signer = app(CallbackDataSigner::class);
        $this->router = app(TelegramCallbackRouter::class);
    }

    private function makeEmployerWithApplication(ApplicationStatus $status = ApplicationStatus::Pending): array
    {
        $employer  = User::factory()->employer()->create([
            'telegram_id'         => 333333333,
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

    #[Test]
    public function it_routes_reject_callback_to_handler_and_updates_application(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake([
            "{$this->botUrl}/edit-message"    => Http::response(['ok' => true], 200),
            "{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200),
        ]);

        $callbackData = $this->signer->sign('reject', 'application', $application->id);

        $this->router->dispatch(
            callbackData:    $callbackData,
            user:            $employer,
            messageId:       42,
            callbackQueryId: 'cq_test_reject',
        );

        $this->assertEquals(ApplicationStatus::Rejected, $application->fresh()->status);
    }

    #[Test]
    public function it_routes_invite_callback_to_handler_and_updates_application(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake([
            "{$this->botUrl}/edit-message"    => Http::response(['ok' => true], 200),
            "{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200),
        ]);

        $callbackData = $this->signer->sign('invite', 'application', $application->id);

        $this->router->dispatch(
            callbackData:    $callbackData,
            user:            $employer,
            messageId:       43,
            callbackQueryId: 'cq_test_invite',
        );

        $this->assertEquals(ApplicationStatus::Interview, $application->fresh()->status);
    }

    #[Test]
    public function it_rejects_tampered_callback_data_for_application_actions(): void
    {
        [$employer, $application] = $this->makeEmployerWithApplication();

        Http::fake([
            "{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200),
        ]);

        $callbackData = $this->signer->sign('reject', 'application', $application->id);
        $tampered     = substr($callbackData, 0, -3) . 'XXX';

        $this->router->dispatch(
            callbackData:    $tampered,
            user:            $employer,
            messageId:       44,
            callbackQueryId: 'cq_tampered',
        );

        $this->assertNotEquals(ApplicationStatus::Rejected, $application->fresh()->status);
    }
}

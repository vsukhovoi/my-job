<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Telegram\UrlGenerators;

use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Telegram\CallbackDataSigner;
use App\Services\Telegram\UrlGenerators\ApplicationUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationUrlGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationUrlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(ApplicationUrlGenerator::class);
    }

    private function makeApplication(): Application
    {
        $employer  = User::factory()->employer()->create();
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $vacancy   = Vacancy::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['role' => UserRole::Candidate]);

        return Application::factory()->create([
            'vacancy_id' => $vacancy->id,
            'user_id'    => $candidate->id,
        ]);
    }

    #[Test]
    public function it_generates_cv_view_url_for_employer(): void
    {
        $application = $this->makeApplication();
        $url = $this->generator->cvViewUrl($application);

        $this->assertStringContainsString((string) $application->id, $url);
        $this->assertStringContainsString('candidates', $url);
    }

    #[Test]
    public function it_generates_chat_url_for_employer_to_candidate(): void
    {
        $application = $this->makeApplication();
        $url = $this->generator->chatUrl($application);

        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('http', $url);
    }

    #[Test]
    public function it_generates_callback_data_within_telegram_limit(): void
    {
        $signer = app(CallbackDataSigner::class);

        $rejectData = $signer->sign('reject', 'application', 999999999);
        $inviteData = $signer->sign('invite', 'application', 999999999);

        $this->assertLessThanOrEqual(64, strlen($rejectData), "reject callback_data exceeds 64 bytes");
        $this->assertLessThanOrEqual(64, strlen($inviteData), "invite callback_data exceeds 64 bytes");
    }
}

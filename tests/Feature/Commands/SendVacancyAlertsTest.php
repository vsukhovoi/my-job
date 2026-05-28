<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Category;
use App\Models\Company;
use App\Models\TelegramSubscription;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendVacancyAlertsTest extends TestCase
{
    use RefreshDatabase;

    private string $botUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl = config('services.telegram_bot.api_url', 'http://localhost:8080');
        Cache::flush();
    }

    private function makeSubscribedVacancy(int $telegramId = 111222333): array
    {
        $employer  = User::factory()->employer()->create();
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $category  = Category::factory()->create();
        $vacancy   = Vacancy::factory()->active()->create([
            'company_id'   => $company->id,
            'category_id'  => $category->id,
            'published_at' => now(),
        ]);

        TelegramSubscription::create([
            'telegram_id' => $telegramId,
            'category_id' => $category->id,
        ]);

        return [$vacancy, $telegramId];
    }

    // ── 7.3 ──────────────────────────────────────────────────────────────────

    #[Test]
    public function it_sends_alert_to_subscribed_user_for_new_vacancy(): void
    {
        [$vacancy, $telegramId] = $this->makeSubscribedVacancy(111222333);

        Http::fake(["{$this->botUrl}/send-message" => Http::response(['success' => true], 200)]);

        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();

        Http::assertSent(function ($request) use ($vacancy, $telegramId) {
            $this->assertEquals($telegramId, $request->data()['chat_id']);
            $this->assertStringContainsString($vacancy->title, $request->data()['text']);
            $this->assertStringContainsString("/jobs/{$vacancy->slug}", $request->data()['text']);
            return true;
        });
    }

    #[Test]
    public function it_includes_category_name_in_message(): void
    {
        [$vacancy] = $this->makeSubscribedVacancy();

        Http::fake(["{$this->botUrl}/send-message" => Http::response(['success' => true], 200)]);

        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();

        Http::assertSent(function ($request) use ($vacancy) {
            $this->assertStringContainsString($vacancy->category->name, $request->data()['text']);
            return true;
        });
    }

    #[Test]
    public function it_does_not_send_for_vacancy_published_more_than_hour_ago(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);
        $category = Category::factory()->create();
        $vacancy  = Vacancy::factory()->active()->create([
            'company_id'   => $company->id,
            'category_id'  => $category->id,
            'published_at' => now()->subMinutes(61),
        ]);

        TelegramSubscription::create(['telegram_id' => 111222333, 'category_id' => $category->id]);

        Http::fake();

        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_does_not_send_for_inactive_vacancy(): void
    {
        $employer = User::factory()->employer()->create();
        $company  = Company::factory()->create(['user_id' => $employer->id]);
        $category = Category::factory()->create();

        Vacancy::factory()->create([
            'company_id'   => $company->id,
            'category_id'  => $category->id,
            'is_active'    => false,
            'published_at' => now(),
        ]);

        TelegramSubscription::create(['telegram_id' => 111222333, 'category_id' => $category->id]);

        Http::fake();

        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();

        Http::assertNothingSent();
    }

    // ── 7.4 ──────────────────────────────────────────────────────────────────

    #[Test]
    public function it_does_not_send_duplicate_alert_on_second_run(): void
    {
        [$vacancy, $telegramId] = $this->makeSubscribedVacancy();

        Http::fake(["{$this->botUrl}/send-message" => Http::response(['success' => true], 200)]);

        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();
        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_sends_to_multiple_subscribers_in_same_category(): void
    {
        $employer  = User::factory()->employer()->create();
        $company   = Company::factory()->create(['user_id' => $employer->id]);
        $category  = Category::factory()->create();
        $vacancy   = Vacancy::factory()->active()->create([
            'company_id'   => $company->id,
            'category_id'  => $category->id,
            'published_at' => now(),
        ]);

        TelegramSubscription::create(['telegram_id' => 111111111, 'category_id' => $category->id]);
        TelegramSubscription::create(['telegram_id' => 222222222, 'category_id' => $category->id]);
        TelegramSubscription::create(['telegram_id' => 333333333, 'category_id' => $category->id]);

        Http::fake(["{$this->botUrl}/send-message" => Http::response(['success' => true], 200)]);

        $this->artisan('app:send-vacancy-alerts')->assertSuccessful();

        Http::assertSentCount(3);
    }
}

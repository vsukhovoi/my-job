<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\TelegramCallbackHandlerInterface;
use App\DataTransferObjects\CallbackDataPayload;
use App\Models\User;
use App\Services\Telegram\CallbackDataSigner;
use App\Services\Telegram\TelegramCallbackRouter;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramCallbackRouterTest extends TestCase
{
    use RefreshDatabase;

    private CallbackDataSigner $signer;
    private string $botUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = app(CallbackDataSigner::class);
        $this->botUrl = config('services.telegram_bot.api_url', 'http://localhost:8080');
    }

    #[Test]
    public function it_signs_and_verifies_callback_data_successfully(): void
    {
        $data    = $this->signer->sign('extend', 'vacancy', 42, '15');
        $payload = $this->signer->verify($data);

        $this->assertNotNull($payload);
        $this->assertEquals('extend', $payload->action);
        $this->assertEquals('vacancy', $payload->resourceType);
        $this->assertEquals(42, $payload->resourceId);
        $this->assertEquals('15', $payload->param);
    }

    #[Test]
    public function it_rejects_tampered_callback_data(): void
    {
        $data    = $this->signer->sign('extend', 'vacancy', 42);
        $tampered = substr($data, 0, -3) . 'XXX';

        $payload = $this->signer->verify($tampered);

        $this->assertNull($payload);
    }

    #[Test]
    public function it_rejects_callback_data_exceeding_64_bytes(): void
    {
        $this->expectException(\OverflowException::class);

        $this->signer->sign(
            action:       'some_very_long_action_name',
            resourceType: 'some_very_long_resource_type',
            resourceId:   999999,
            param:        'extra',
        );
    }

    #[Test]
    public function it_dispatches_to_correct_handler_based_on_action(): void
    {
        $user = User::factory()->create();

        $handler = new class implements TelegramCallbackHandlerInterface {
            public bool $called = false;

            public function canHandle(CallbackDataPayload $payload): bool
            {
                return $payload->action === 'test_action';
            }

            public function handle(CallbackDataPayload $payload, User $user, int $messageId): void
            {
                $this->called = true;
            }
        };

        Http::fake(["{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200)]);

        $signer   = app(CallbackDataSigner::class);
        $notifier = app(TelegramNotifier::class);
        $router   = new TelegramCallbackRouter($signer, $notifier, [$handler]);

        $data = $signer->sign('test_action', 'vacancy', 1);
        $router->dispatch($data, $user, 100, 'cq_1');

        $this->assertTrue($handler->called);
    }

    #[Test]
    public function it_logs_and_returns_safely_when_no_handler_found(): void
    {
        $user = User::factory()->create();

        Http::fake(["{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200)]);

        $signer   = app(CallbackDataSigner::class);
        $notifier = app(TelegramNotifier::class);
        $router   = new TelegramCallbackRouter($signer, $notifier, []);

        $data = $signer->sign('unknown', 'vacancy', 1);

        // Не кидає exception
        $router->dispatch($data, $user, 100, 'cq_1');

        Http::assertSent(fn ($req) => str_contains($req->url(), '/answer-callback'));
    }

    #[Test]
    public function it_enforces_rate_limit_on_callback_queries(): void
    {
        $user = User::factory()->create();

        Http::fake(["{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200)]);

        $signer   = app(CallbackDataSigner::class);
        $notifier = app(TelegramNotifier::class);

        $handler = new class implements TelegramCallbackHandlerInterface {
            public int $callCount = 0;

            public function canHandle(CallbackDataPayload $payload): bool { return true; }
            public function handle(CallbackDataPayload $payload, User $user, int $messageId): void
            {
                $this->callCount++;
            }
        };

        $router = new TelegramCallbackRouter($signer, $notifier, [$handler]);
        $data   = $signer->sign('act', 'vac', 1);

        // Виконуємо 31 запит (ліміт 30)
        for ($i = 0; $i < 31; $i++) {
            $router->dispatch($data, $user, 100, "cq_{$i}");
        }

        // Ровно 30 handler-викликів, 31-й — заблокований
        $this->assertEquals(30, $handler->callCount);
    }

    #[Test]
    public function it_rejects_webhook_without_valid_token_header(): void
    {
        $response = $this->postJson('/api/telegram/webhook/callback', [
            'telegram_user_id'  => 123,
            'callback_data'     => 'test:test:1::sig12',
            'message_id'        => 1,
            'callback_query_id' => 'cq_1',
        ], ['X-Telegram-Webhook-Token' => 'wrong-token']);

        $response->assertStatus(401);
    }
}

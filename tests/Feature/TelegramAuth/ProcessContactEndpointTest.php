<?php

declare(strict_types=1);

namespace Tests\Feature\TelegramAuth;

use App\Models\TelegramSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessContactEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookToken = 'test-webhook-token-secret';
        config(['services.telegram.webhook_token' => $this->webhookToken]);
    }

    private function pendingSession(string $token = 'auth-token-abc123', string $role = 'candidate'): TelegramSession
    {
        return TelegramSession::create([
            'session_token' => $token,
            'status'        => 'pending',
            'role'          => $role,
            'expires_at'    => now()->addMinutes(10),
        ]);
    }

    #[Test]
    public function it_returns_matched_for_valid_phone_and_token(): void
    {
        $this->pendingSession('valid-token');

        $response = $this->postJson('/api/telegram/auth/contact', [
            'auth_token'       => 'valid-token',
            'telegram_user_id' => 123456789,
            'phone'            => '+380991234567',
            'first_name'       => 'Іван',
        ], ['X-Telegram-Webhook-Token' => $this->webhookToken]);

        $response->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonStructure(['matched', 'user_id']);

        $this->assertDatabaseHas('users', ['telegram_id' => 123456789]);
    }

    #[Test]
    public function it_returns_unmatched_for_expired_token(): void
    {
        TelegramSession::create([
            'session_token' => 'expired-token',
            'status'        => 'pending',
            'role'          => 'candidate',
            'expires_at'    => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/telegram/auth/contact', [
            'auth_token'       => 'expired-token',
            'telegram_user_id' => 987654321,
            'phone'            => '+380991111111',
        ], ['X-Telegram-Webhook-Token' => $this->webhookToken]);

        $response->assertOk()
            ->assertJsonPath('matched', false);
    }

    #[Test]
    public function it_returns_400_for_invalid_payload(): void
    {
        $response = $this->postJson('/api/telegram/auth/contact', [
            'auth_token' => 'some-token',
            // missing telegram_user_id and phone
        ], ['X-Telegram-Webhook-Token' => $this->webhookToken]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_401_without_webhook_token_header(): void
    {
        $response = $this->postJson('/api/telegram/auth/contact', [
            'auth_token'       => 'some-token',
            'telegram_user_id' => 111222333,
            'phone'            => '+380990000000',
        ]);

        $response->assertStatus(401);
    }
}

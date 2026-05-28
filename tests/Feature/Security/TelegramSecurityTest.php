<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\TelegramSession;
use App\Services\TelegramAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('api');
    }

    // ── 9.1 Rate limiting on status polling ──────────────────────────────────

    #[Test]
    public function it_returns_429_after_exceeding_status_polling_rate_limit(): void
    {
        $token = Str::random(48);

        // 110 запитів (ceil(TTL_300s / polling_3s) + 10 буфер) — всі мають пройти
        for ($i = 0; $i < 110; $i++) {
            $this->getJson("/api/telegram/auth/status/{$token}");
        }

        // 111-й — має бути заблокований
        $this->getJson("/api/telegram/auth/status/{$token}")
            ->assertStatus(429);
    }

    // ── 9.2 Expired deep-link session ────────────────────────────────────────

    #[Test]
    public function it_returns_expired_status_for_session_with_past_expires_at(): void
    {
        $session = TelegramSession::create([
            'session_token' => Str::random(48),
            'role'          => 'candidate',
            'status'        => 'pending',
            'expires_at'    => now()->subMinutes(10),
        ]);

        $this->getJson("/api/telegram/auth/status/{$session->session_token}")
            ->assertOk()
            ->assertJson(['status' => 'expired']);
    }

    #[Test]
    public function it_returns_expired_status_for_explicitly_expired_session(): void
    {
        $session = TelegramSession::create([
            'session_token' => Str::random(48),
            'role'          => 'candidate',
            'status'        => 'expired',
            'expires_at'    => now()->addMinutes(5),
        ]);

        $result = app(TelegramAuthService::class)->getStatus($session->session_token);

        $this->assertEquals('expired', $result['status']);
    }

    #[Test]
    public function it_returns_not_found_for_unknown_token(): void
    {
        $this->getJson('/api/telegram/auth/status/' . Str::random(48))
            ->assertOk()
            ->assertJson(['status' => 'not_found']);
    }

    #[Test]
    public function it_returns_pending_for_valid_non_expired_session(): void
    {
        $session = TelegramSession::create([
            'session_token' => Str::random(48),
            'role'          => 'candidate',
            'status'        => 'pending',
            'expires_at'    => now()->addMinutes(5),
        ]);

        $this->getJson("/api/telegram/auth/status/{$session->session_token}")
            ->assertOk()
            ->assertJson(['status' => 'pending']);
    }
}

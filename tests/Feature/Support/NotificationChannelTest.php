<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\UserRole;
use App\Mail\SupportMessageNotification;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\SupportService;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function email_channel_sends_mail_on_admin_reply(): void
    {
        Mail::fake();
        $this->instance(TelegramNotifier::class, Mockery::mock(TelegramNotifier::class, function ($mock) {
            $mock->shouldNotReceive('send');
        }));

        $user   = User::factory()->create(['notify_via_email' => true, 'notify_via_telegram' => false]);
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $thread = SupportThread::factory()->create(['user_id' => $user->id]);

        app(SupportService::class)->reply(
            thread: $thread,
            sender: $admin,
            body: 'Ваше питання вирішено.',
        );

        Mail::assertSent(
            SupportMessageNotification::class,
            fn ($mail) => $mail->hasTo($user->email)
        );
    }

    #[Test]
    public function telegram_channel_sends_telegram_on_admin_reply(): void
    {
        Mail::fake();

        $telegramMock = Mockery::mock(TelegramNotifier::class);
        $telegramMock->shouldReceive('send')
            ->once()
            ->with('123456789', Mockery::type('string'));
        $this->instance(TelegramNotifier::class, $telegramMock);

        $user   = User::factory()->create([
            'notify_via_telegram' => true,
            'notify_via_email'    => false,
            'telegram_id'         => '123456789',
        ]);
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $thread = SupportThread::factory()->create(['user_id' => $user->id]);

        app(SupportService::class)->reply(
            thread: $thread,
            sender: $admin,
            body: 'Відповідь через Telegram.',
        );

        Mail::assertNotSent(
            SupportMessageNotification::class,
            fn ($mail) => $mail->hasTo($user->email)
        );
    }

    #[Test]
    public function telegram_channel_without_telegram_id_sends_nothing(): void
    {
        Mail::fake();
        $this->instance(TelegramNotifier::class, Mockery::mock(TelegramNotifier::class, function ($mock) {
            $mock->shouldNotReceive('send');
        }));

        $user   = User::factory()->create([
            'notify_via_telegram' => true,
            'notify_via_email'    => false,
            'telegram_id'         => null,
        ]);
        $admin  = User::factory()->create(['role' => UserRole::Admin]);
        $thread = SupportThread::factory()->create(['user_id' => $user->id]);

        app(SupportService::class)->reply(thread: $thread, sender: $admin, body: 'Тест');

        Mail::assertNotSent(
            SupportMessageNotification::class,
            fn ($mail) => $mail->hasTo($user->email)
        );
    }

    #[Test]
    public function cannot_enable_telegram_without_telegram_id(): void
    {
        $user = User::factory()->create(['telegram_id' => null, 'notify_via_telegram' => false]);
        $this->actingAs($user);

        Volt::test('shared.notification-preferences')
            ->call('toggle', 'telegram')
            ->call('save');

        $this->assertFalse((bool) $user->fresh()->notify_via_telegram);
    }

    #[Test]
    public function can_enable_telegram_with_telegram_id(): void
    {
        $user = User::factory()->create([
            'notify_via_email'    => true,
            'notify_via_telegram' => false,
            'telegram_id'         => '555666777',
        ]);
        $this->actingAs($user);

        Volt::test('shared.notification-preferences')
            ->call('toggle', 'telegram')
            ->call('save');

        $this->assertTrue((bool) $user->fresh()->notify_via_telegram);
    }

    #[Test]
    public function can_enable_email_when_telegram_is_on(): void
    {
        $user = User::factory()->create([
            'notify_via_telegram' => true,
            'notify_via_email'    => false,
            'telegram_id'         => '555666777',
        ]);
        $this->actingAs($user);

        Volt::test('shared.notification-preferences')
            ->call('toggle', 'email')
            ->call('save');

        $this->assertTrue((bool) $user->fresh()->notify_via_email);
    }
}

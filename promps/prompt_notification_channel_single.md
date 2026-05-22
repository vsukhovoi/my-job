# Промпт для Claude Code: Вибір каналу сповіщень підтримки (один на вибір)

## Контекст проєкту

- Laravel 13, Livewire/Volt, Filament, PostgreSQL, Python Telegram bot (@myjob_in_bot)
- Лише Volt-компоненти (не стандартні Livewire)
- PHPUnit 12 з `#[Test]` атрибутами
- `actingAs()` викликати ДО `Volt::test()`
- Модуль `SupportThread` / `SupportMessage` / `SupportService` вже існує
- Telegram bot вже існує як окремий Python-сервіс

---

## Завдання

Додати до профілю користувача вибір **одного** каналу сповіщень підтримки:
- або email, або Telegram
- зберігати в `users` таблиці як enum-рядок (`'email'` / `'telegram'`)
- `SupportService::reply()` надсилає сповіщення у вибраний канал
- Telegram-сповіщення — через HTTP до Python bot API

---

## КРОК 1. Міграція

Файл: `database/migrations/YYYY_MM_DD_add_notification_channel_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('notification_channel')->default('email')->after('telegram_id');
    // 'email' | 'telegram'
});
```

> Якщо `telegram_id` ще не існує — додати теж:
> `$table->string('telegram_id')->nullable()->after('remember_token');`

---

## КРОК 2. Enum каналу

Файл: `app/Enums/NotificationChannel.php`

```php
<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email    = 'email';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match($this) {
            self::Email    => 'Email',
            self::Telegram => 'Telegram',
        };
    }
}
```

---

## КРОК 3. Оновити модель User

У `app/Models/User.php` додати до `$fillable`:
```php
'notification_channel',
```

До `$casts`:
```php
'notification_channel' => \App\Enums\NotificationChannel::class,
```

Додати helper-методи:
```php
public function prefersEmail(): bool
{
    return $this->notification_channel === \App\Enums\NotificationChannel::Email;
}

public function prefersTelegram(): bool
{
    return $this->notification_channel === \App\Enums\NotificationChannel::Telegram
        && ! empty($this->telegram_id);
}
```

---

## КРОК 4. TelegramNotifier

Файл: `app/Services/TelegramNotifier.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function send(string $chatId, string $text): bool
    {
        $botApiUrl = config('services.telegram_bot.api_url');

        try {
            $response = Http::timeout(5)->post("{$botApiUrl}/send-message", [
                'chat_id' => $chatId,
                'text'    => $text,
            ]);

            if (! $response->successful()) {
                Log::warning('TelegramNotifier: failed', [
                    'chat_id' => $chatId,
                    'status'  => $response->status(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramNotifier: exception', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
```

Додати до `config/services.php`:
```php
'telegram_bot' => [
    'api_url' => env('TELEGRAM_BOT_API_URL', 'http://localhost:8080'),
],
```

Додати до `.env` і `.env.example`:
```
TELEGRAM_BOT_API_URL=http://localhost:8080
```

---

## КРОК 5. Оновити SupportService

Файл: `app/Services/SupportService.php`

```php
<?php

namespace App\Services;

use App\Enums\ContactRole;
use App\Enums\SupportThreadStatus;
use App\Mail\SupportMessageNotification;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class SupportService
{
    public function __construct(
        private readonly TelegramNotifier $telegram,
    ) {}

    public function createThread(
        User $user,
        string $subject,
        string $body,
        ContactRole $role,
    ): SupportThread {
        $thread = SupportThread::create([
            'user_id'         => $user->id,
            'subject'         => $subject,
            'role'            => $role,
            'status'          => SupportThreadStatus::Open,
            'last_message_at' => now(),
        ]);

        SupportMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'body'      => $body,
            'is_read'   => false,
        ]);

        $this->notifyAdmin($thread);

        return $thread;
    }

    public function reply(
        SupportThread $thread,
        User $sender,
        string $body,
    ): SupportMessage {
        $message = SupportMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $sender->id,
            'body'      => $body,
            'is_read'   => false,
        ]);

        $thread->update(['last_message_at' => now()]);

        if ($this->isAdmin($sender)) {
            $this->notifyUser($thread, $message);
        } else {
            $this->notifyAdmin($thread);
        }

        return $message;
    }

    public function markThreadRead(SupportThread $thread, User $reader): void
    {
        $thread->messages()
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    // -------------------------------------------------------------------------

    private function notifyUser(SupportThread $thread, SupportMessage $message): void
    {
        $user = $thread->user;
        if (! $user) return;

        $threadUrl = url('/seeker/messages/' . $thread->id);

        if ($user->prefersEmail()) {
            Mail::to($user->email)
                ->send(new SupportMessageNotification($thread, $message, $user->name));

        } elseif ($user->prefersTelegram()) {
            $text = implode("\n", [
                "💬 Нова відповідь у зверненні «{$thread->subject}»",
                "",
                $message->body,
                "",
                "👉 {$threadUrl}",
            ]);
            $this->telegram->send($user->telegram_id, $text);
        }
    }

    private function notifyAdmin(SupportThread $thread): void
    {
        $lastMessage = $thread->messages()->latest()->first();
        if (! $lastMessage) return;

        Mail::to(config('mail.support_address', 'support@myjob.co.ua'))
            ->send(new SupportMessageNotification($thread, $lastMessage, 'Команда підтримки'));
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->is_admin;
    }
}
```

---

## КРОК 6. Volt-компонент налаштувань

Файл: `resources/views/livewire/shared/notification-preferences.blade.php`

```php
<?php

use App\Enums\NotificationChannel;
use Livewire\Volt\Component;

new class extends Component {

    public string $channel = 'email';
    public bool   $saved   = false;

    public function mount(): void
    {
        $this->channel = auth()->user()->notification_channel->value;
    }

    public function save(): void
    {
        $this->validate([
            'channel' => ['required', 'in:email,telegram'],
        ]);

        $user = auth()->user();

        // Telegram можна обрати лише якщо підключено
        if ($this->channel === 'telegram' && empty($user->telegram_id)) {
            $this->addError('channel', 'Спочатку підключіть Telegram у налаштуваннях акаунту.');
            return;
        }

        $user->update([
            'notification_channel' => $this->channel,
        ]);

        $this->saved = true;
    }
};
?>

<div class="space-y-4">
    <div>
        <h3 class="text-sm font-medium text-gray-700">Канал сповіщень підтримки</h3>
        <p class="text-xs text-gray-400 mt-0.5">
            Отримуйте сповіщення коли надходить відповідь на ваше звернення.
        </p>
    </div>

    @error('channel')
        <p class="text-xs text-red-500">{{ $message }}</p>
    @enderror

    <div class="flex gap-3">

        {{-- Email --}}
        <label class="flex-1 cursor-pointer">
            <input type="radio"
                   wire:model="channel"
                   value="email"
                   class="sr-only peer" />
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-200
                        peer-checked:border-green-500 peer-checked:bg-green-50 transition">
                <x-heroicon-o-envelope class="w-5 h-5 text-gray-400 peer-checked:text-green-600" />
                <div>
                    <p class="text-sm font-medium text-gray-700">Email</p>
                    <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </label>

        {{-- Telegram --}}
        @php $hasTelegram = ! empty(auth()->user()->telegram_id); @endphp
        <label @class(['flex-1', 'cursor-pointer' => $hasTelegram, 'cursor-not-allowed' => ! $hasTelegram])>
            <input type="radio"
                   wire:model="channel"
                   value="telegram"
                   @disabled(! $hasTelegram)
                   class="sr-only peer" />
            <div @class([
                'flex items-center gap-3 px-4 py-3 rounded-xl border transition',
                'border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50' => $hasTelegram,
                'border-gray-100 bg-gray-50 opacity-60' => ! $hasTelegram,
            ])>
                <x-heroicon-o-paper-airplane class="w-5 h-5 text-gray-400" />
                <div>
                    <p class="text-sm font-medium text-gray-700">Telegram</p>
                    @if($hasTelegram)
                        <p class="text-xs text-green-600">підключено ✓</p>
                    @else
                        <a href="{{ route('seeker.settings') }}"
                           class="text-xs text-orange-500 hover:underline">
                            підключити →
                        </a>
                    @endif
                </div>
            </div>
        </label>

    </div>

    <button wire:click="save"
            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
        Зберегти
    </button>

    @if($saved)
        <p class="text-xs text-green-600" wire:key="saved-ok">✓ Налаштування збережено</p>
    @endif
</div>
```

---

## КРОК 7. Підключити в кабінетах

### Шукач — вкладка «Налаштування»

```blade
<div class="bg-white rounded-xl border border-gray-100 p-6">
    <livewire:shared.notification-preferences />
</div>
```

### Роботодавець — вкладка «Налаштування»

Те саме — компонент `shared`, підходить обом ролям.

---

## КРОК 8. Python bot — endpoint

> Зміни у Python-сервісі бота, не в Laravel.

```python
# FastAPI
from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI()

class SendMessageRequest(BaseModel):
    chat_id: str
    text: str

@app.post("/send-message")
async def send_message(req: SendMessageRequest):
    await bot.send_message(chat_id=req.chat_id, text=req.text)
    return {"ok": True}
```

Laravel надсилає `POST TELEGRAM_BOT_API_URL/send-message` з `{chat_id, text}`.

---

## КРОК 9. Тести

Файл: `tests/Feature/Support/NotificationChannelTest.php`

```php
<?php

namespace Tests\Feature\Support;

use App\Enums\ContactRole;
use App\Enums\NotificationChannel;
use App\Mail\SupportMessageNotification;
use App\Models\SupportMessage;
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

        $user  = User::factory()->create(['notification_channel' => NotificationChannel::Email]);
        $admin = User::factory()->create(['is_admin' => true]);
        $thread = SupportThread::factory()->create(['user_id' => $user->id]);

        app(SupportService::class)->reply(
            thread: $thread,
            sender: $admin,
            body: 'Ваше питання вирішено.',
        );

        Mail::assertSent(SupportMessageNotification::class,
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

        $user  = User::factory()->create([
            'notification_channel' => NotificationChannel::Telegram,
            'telegram_id'          => '123456789',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $thread = SupportThread::factory()->create(['user_id' => $user->id]);

        app(SupportService::class)->reply(
            thread: $thread,
            sender: $admin,
            body: 'Відповідь через Telegram.',
        );

        Mail::assertNotSent(SupportMessageNotification::class,
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

        $user  = User::factory()->create([
            'notification_channel' => NotificationChannel::Telegram,
            'telegram_id'          => null, // не підключено
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $thread = SupportThread::factory()->create(['user_id' => $user->id]);

        app(SupportService::class)->reply(thread: $thread, sender: $admin, body: 'Тест');

        Mail::assertNotSent(SupportMessageNotification::class,
            fn ($mail) => $mail->hasTo($user->email)
        );
    }

    #[Test]
    public function cannot_select_telegram_without_telegram_id(): void
    {
        $user = User::factory()->create(['telegram_id' => null]);
        $this->actingAs($user);

        Volt::test('shared.notification-preferences')
            ->set('channel', 'telegram')
            ->call('save')
            ->assertHasErrors(['channel']);

        $this->assertEquals(
            NotificationChannel::Email,
            $user->fresh()->notification_channel
        );
    }

    #[Test]
    public function can_switch_to_telegram_with_telegram_id(): void
    {
        $user = User::factory()->create([
            'notification_channel' => NotificationChannel::Email,
            'telegram_id'          => '555666777',
        ]);
        $this->actingAs($user);

        Volt::test('shared.notification-preferences')
            ->set('channel', 'telegram')
            ->call('save')
            ->assertSet('saved', true)
            ->assertHasNoErrors();

        $this->assertEquals(
            NotificationChannel::Telegram,
            $user->fresh()->notification_channel
        );
    }

    #[Test]
    public function can_switch_back_to_email(): void
    {
        $user = User::factory()->create([
            'notification_channel' => NotificationChannel::Telegram,
            'telegram_id'          => '555666777',
        ]);
        $this->actingAs($user);

        Volt::test('shared.notification-preferences')
            ->set('channel', 'email')
            ->call('save')
            ->assertSet('saved', true);

        $this->assertEquals(
            NotificationChannel::Email,
            $user->fresh()->notification_channel
        );
    }
}
```

---

## Чого НЕ робити

- Не використовувати стандартні Livewire компоненти — тільки Volt
- Не використовувати docblock `/** @test */` — тільки `#[Test]`
- Не чейнити `actingAs()` з `Volt::test()`
- Не надсилати Telegram якщо `telegram_id` порожній — мовчки ігнорувати
- Не ламати request при помилці TelegramNotifier — лише Log::error
- Не чіпати Python bot поза КРОК 8

---

## Результат

| Що | Де |
|---|---|
| Міграція | `notification_channel` enum-рядок у `users` |
| `NotificationChannel` Enum | `email` / `telegram` |
| `User` helpers | `prefersEmail()`, `prefersTelegram()` |
| `TelegramNotifier` | HTTP POST до Python bot `/send-message` |
| `SupportService` | надсилає в один обраний канал |
| UI | `shared.notification-preferences` — дві карточки-радіо |
| Захист | Telegram заблоковано якщо `telegram_id` не підключено |
| Тести | 6 тестів у `tests/Feature/Support/NotificationChannelTest.php` |

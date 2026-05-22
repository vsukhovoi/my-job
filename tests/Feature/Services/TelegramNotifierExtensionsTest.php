<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\TelegramMessageResult;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramNotifierExtensionsTest extends TestCase
{
    private string $botUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->botUrl = config('services.telegram_bot.api_url', 'http://localhost:8080');
    }

    #[Test]
    public function it_sends_message_with_inline_keyboard_payload(): void
    {
        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response([
                'message_id' => 42,
                'chat_id'    => 111,
            ], 200),
        ]);

        $notifier = app(TelegramNotifier::class);
        $result   = $notifier->sendMessageWithKeyboard(
            telegramId: 111,
            text: '📨 <b>Тест</b>',
            inlineKeyboard: [[['text' => 'Відповісти', 'url' => 'https://example.com']]],
        );

        $this->assertInstanceOf(TelegramMessageResult::class, $result);
        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/send-message-with-keyboard')
                && $body['chat_id'] === 111
                && $body['parse_mode'] === 'HTML'
                && isset($body['inline_keyboard']);
        });
    }

    #[Test]
    public function it_returns_message_id_for_later_editing(): void
    {
        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response([
                'message_id' => 99,
                'chat_id'    => 222,
            ], 200),
        ]);

        $notifier = app(TelegramNotifier::class);
        $result   = $notifier->sendMessageWithKeyboard(222, 'Текст', []);

        $this->assertTrue($result->success);
        $this->assertEquals(99, $result->messageId);
        $this->assertEquals(222, $result->chatId);
    }

    #[Test]
    public function it_edits_existing_message_text(): void
    {
        Http::fake([
            "{$this->botUrl}/edit-message" => Http::response(['ok' => true], 200),
        ]);

        $notifier = app(TelegramNotifier::class);
        $success  = $notifier->editMessageText(
            telegramId: 333,
            messageId: 50,
            newText: 'Оновлений текст',
            inlineKeyboard: [[['text' => 'Нова кнопка', 'callback_data' => 'act:test']]],
        );

        $this->assertTrue($success);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/edit-message')
                && $body['chat_id'] === 333
                && $body['message_id'] === 50
                && isset($body['inline_keyboard']);
        });
    }

    #[Test]
    public function it_gracefully_handles_telegram_api_errors(): void
    {
        Http::fake([
            "{$this->botUrl}/send-message-with-keyboard" => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $notifier = app(TelegramNotifier::class);
        $result   = $notifier->sendMessageWithKeyboard(444, 'Текст', []);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertNull($result->messageId);
    }

    #[Test]
    public function it_answers_callback_query_with_optional_alert(): void
    {
        Http::fake([
            "{$this->botUrl}/answer-callback" => Http::response(['ok' => true], 200),
        ]);

        $notifier = app(TelegramNotifier::class);
        $success  = $notifier->answerCallbackQuery(
            callbackQueryId: 'cq_abc123',
            text: 'Виконано!',
            showAlert: true,
        );

        $this->assertTrue($success);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/answer-callback')
                && $body['callback_query_id'] === 'cq_abc123'
                && $body['text'] === 'Виконано!'
                && $body['show_alert'] === true;
        });
    }
}

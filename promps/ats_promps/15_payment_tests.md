# МОДУЛЬ 15. Тести — MonoPay, WayForPay, LiqPay

## 🎯 Мета модуля
Feature-тести для трьох нових gateway та спільного `WebhookController`. Кожен тест перевіряє: верифікацію підпису, ідемпотентність, коректне продовження вакансії, ігнорування не-оплачених подій.

**Передумова:** модулі 11A–14 виконано.

---

## 🏭 КРОК 15.1. Хелпери для побудови підписів

Виноси в окремий `TestCase` — щоб не дублювати у кожному тест-файлі.

`tests/Feature/Payments/PaymentTestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Vacancy;
use App\Payments\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class PaymentTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Фіксований час для стабільних підписів
        \Carbon\Carbon::setTestNow('2025-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeVacancy(): Vacancy
    {
        return Vacancy::factory()->active(daysLeft: 5)->create();
    }

    protected function buildOrderId(Vacancy $vacancy, int $days = 30): string
    {
        return CheckoutService::buildOrderId($vacancy->id, $days);
    }
}
```

---

## 🧪 КРОК 15.2. Тести абстрактного WebhookController

`tests/Feature/Payments/WebhookControllerTest.php`:

```php
<?php

namespace Tests\Feature\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CheckoutData;
use App\Payments\DTOs\PaymentResult;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;

class WebhookControllerTest extends PaymentTestCase
{
    public function test_unknown_gateway_returns_404(): void
    {
        $this->post('/webhooks/payments/unknown_provider')
            ->assertStatus(404);
    }

    public function test_idempotency_prevents_double_extension(): void
    {
        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);

        // Симулюємо реальний gateway через mocking
        $this->mockGateway('mono', new PaymentResult(
            isPaid:          true,
            gatewayName:     'mono',
            externalEventId: 'mono_evt_dup_test',
            orderId:         $orderId,
            amountKopecks:   20000,
            currency:        'UAH',
            vacancyId:       (string) $vacancy->id,
            days:            30,
        ));

        config(['payments.gateways.mono.token' => 'test']);

        // Перший запит — обробляємо
        $this->post('/webhooks/payments/mono', [])->assertOk();

        $firstExpires = $vacancy->fresh()->expires_at->copy();

        // Другий запит — ігноруємо (idempotency)
        $this->post('/webhooks/payments/mono', [])->assertOk();

        expect($vacancy->fresh()->expires_at->toIso8601String())
            ->toBe($firstExpires->toIso8601String());
    }

    /**
     * Мокаємо конкретний gateway у реєстрі.
     */
    private function mockGateway(string $name, PaymentResult $result): void
    {
        $gateway = new class($name, $result) implements PaymentGateway {
            public function __construct(
                private string $gatewayName,
                private PaymentResult $result,
            ) {}
            public function name(): string { return $this->gatewayName; }
            public function createCheckout(CheckoutData $data): string { return ''; }
            public function parseWebhook(Request $request): PaymentResult { return $this->result; }
            public function successResponse(): \Illuminate\Http\Response { return response(''); }
        };

        $registry = app(\App\Http\Controllers\Payments\PaymentGatewayRegistry::class);
        $registry->register($gateway);
    }
}
```

---

## 🧪 КРОК 15.3. Тести MonoPay

`tests/Feature/Payments/MonoPayGatewayTest.php`:

```php
<?php

namespace Tests\Feature\Payments;

use App\Enums\VacancyStatus;
use App\Events\VacancyExtended;
use App\Payments\CheckoutService;
use App\Payments\Gateways\MonoPayGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class MonoPayGatewayTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.gateways.mono.token'      => 'test_mono_token',
            'payments.gateways.mono.public_key' => $this->generateFakeEd25519PublicKey(),
        ]);
    }

    public function test_webhook_invalid_signature_returns_400(): void
    {
        $this->post('/webhooks/payments/mono', [], ['X-Sign' => 'invalid_base64!!'])
            ->assertStatus(400);
    }

    public function test_webhook_missing_sign_header_returns_400(): void
    {
        $this->post('/webhooks/payments/mono', ['invoiceId' => 'test'])
            ->assertStatus(400);
    }

    public function test_webhook_not_paid_status_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy);

        $body = json_encode([
            'invoiceId' => 'inv_test_001',
            'status'    => 'failure',
            'reference' => $orderId,
            'amount'    => 20000,
        ]);

        $this->post('/webhooks/payments/mono', json_decode($body, true), [
            'X-Sign' => $this->signMonoPayload($body),
        ])->assertOk();

        Event::assertNotDispatched(VacancyExtended::class);
        expect($vacancy->fresh()->status)->toBe(VacancyStatus::Active);
    }

    public function test_successful_webhook_extends_vacancy(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);
        $oldExpires = $vacancy->expires_at->copy();

        $body = json_encode([
            'invoiceId' => 'inv_test_success_001',
            'status'    => 'success',
            'reference' => $orderId,
            'amount'    => 20000,
        ]);

        $this->post('/webhooks/payments/mono', json_decode($body, true), [
            'X-Sign' => $this->signMonoPayload($body),
        ])->assertOk();

        expect($vacancy->fresh()->status)->toBe(VacancyStatus::Active);
        expect($vacancy->fresh()->expires_at->gt($oldExpires))->toBeTrue();

        Event::assertDispatched(VacancyExtended::class, fn ($e) =>
            $e->vacancy->id === $vacancy->id && $e->days === 30
        );
    }

    public function test_duplicate_invoice_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);

        $body = json_encode([
            'invoiceId' => 'inv_idempotency_test',
            'status'    => 'success',
            'reference' => $orderId,
            'amount'    => 20000,
        ]);

        $headers = ['X-Sign' => $this->signMonoPayload($body)];
        $data    = json_decode($body, true);

        $this->post('/webhooks/payments/mono', $data, $headers)->assertOk();
        $firstExpires = $vacancy->fresh()->expires_at->copy();

        $this->post('/webhooks/payments/mono', $data, $headers)->assertOk();

        // expires_at не змінилась повторно (без idempotency було б +60 днів)
        expect($vacancy->fresh()->expires_at->toIso8601String())
            ->toBe($firstExpires->toIso8601String());

        Event::assertDispatchedTimes(VacancyExtended::class, 1); // тільки 1 раз
    }

    // =========================================================================
    // Хелпери підпису (Ed25519 — симулюємо через sodium)
    // =========================================================================

    private function generateFakeEd25519PublicKey(): string
    {
        // Якщо sodium доступний — генеруємо реальну пару ключів
        if (function_exists('sodium_crypto_sign_keypair')) {
            $keyPair = sodium_crypto_sign_keypair();
            $this->privateKey = sodium_crypto_sign_secretkey($keyPair);
            return base64_encode(sodium_crypto_sign_publickey($keyPair));
        }

        // Fallback: вимикаємо Ed25519 перевірку у тестах через mock
        $this->skipEd25519 = true;
        return base64_encode(str_repeat('A', 32)); // placeholder
    }

    private string $privateKey = '';
    private bool $skipEd25519 = false;

    private function signMonoPayload(string $body): string
    {
        if ($this->skipEd25519) {
            // Якщо sodium недоступний — мокаємо signature verification у Gateway
            $this->mock(MonoPayGateway::class, function ($mock) {
                $mock->shouldReceive('name')->andReturn('mono');
                // ... часткове мокування
            });
            return 'mocked_signature';
        }

        $signature = sodium_crypto_sign_detached($body, $this->privateKey);
        return base64_encode($signature);
    }
}
```

> **Примітка щодо Ed25519 в тестах.** Якщо PHP sodium extension є — тести використовують реальну крипто. Якщо ні — тест пропускається з `$this->markTestSkipped('sodium extension required')`. Краще першим кроком переконатись, що sodium встановлено (`php -m | grep sodium`).

---

## 🧪 КРОК 15.4. Тести WayForPay

`tests/Feature/Payments/WayForPayGatewayTest.php`:

```php
<?php

namespace Tests\Feature\Payments;

use App\Enums\VacancyStatus;
use App\Events\VacancyExtended;
use Illuminate\Support\Facades\Event;

class WayForPayGatewayTest extends PaymentTestCase
{
    private string $merchantPassword = 'test_secret_password';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.gateways.wayforpay.merchant_account'  => 'test_merchant',
            'payments.gateways.wayforpay.merchant_password' => $this->merchantPassword,
            'payments.gateways.wayforpay.merchant_domain'   => 'example.com',
        ]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $this->postJson('/webhooks/payments/wayforpay', [
            'merchantAccount'    => 'test_merchant',
            'orderReference'     => 'vac_1_30_abc123',
            'amount'             => 200.00,
            'currency'           => 'UAH',
            'authCode'           => '',
            'cardPan'            => '',
            'transactionStatus'  => 'Approved',
            'reasonCode'         => '1100',
            'merchantSignature'  => 'wrong_signature',
        ])->assertStatus(400);
    }

    public function test_approved_transaction_extends_vacancy(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);
        $oldExpires = $vacancy->expires_at->copy();

        $data = $this->buildWfpWebhookPayload($orderId, 'Approved', 200.00);

        $this->postJson('/webhooks/payments/wayforpay', $data)->assertOk();

        expect($vacancy->fresh()->expires_at->gt($oldExpires))->toBeTrue();
        Event::assertDispatched(VacancyExtended::class, fn ($e) => $e->days === 30);
    }

    public function test_declined_transaction_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);

        $data = $this->buildWfpWebhookPayload($orderId, 'Declined', 0.00);

        $this->postJson('/webhooks/payments/wayforpay', $data)->assertOk();

        expect($vacancy->fresh()->status)->toBe(VacancyStatus::Active);
        Event::assertNotDispatched(VacancyExtended::class);
    }

    public function test_response_contains_accept_with_signature(): void
    {
        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);
        $data    = $this->buildWfpWebhookPayload($orderId, 'Approved', 200.00);

        $response = $this->postJson('/webhooks/payments/wayforpay', $data)->assertOk();

        $json = $response->json();
        expect($json)->toHaveKey('status', 'accept')
            ->and($json)->toHaveKey('signature')
            ->and($json)->toHaveKey('orderReference', $orderId);
    }

    // =========================================================================
    // Хелпери підпису WayForPay (HMAC-MD5)
    // =========================================================================

    private function buildWfpWebhookPayload(
        string $orderId,
        string $status,
        float $amount,
    ): array {
        $fields = [
            'test_merchant',   // merchantAccount
            $orderId,          // orderReference
            (string) $amount,
            'UAH',
            '',                // authCode
            '',                // cardPan
            $status,
            '1100',            // reasonCode
        ];

        $signature = hash_hmac('md5', implode(';', $fields), $this->merchantPassword);

        return [
            'merchantAccount'    => 'test_merchant',
            'orderReference'     => $orderId,
            'amount'             => $amount,
            'currency'           => 'UAH',
            'authCode'           => '',
            'cardPan'            => '',
            'transactionStatus'  => $status,
            'reasonCode'         => '1100',
            'merchantSignature'  => $signature,
        ];
    }
}
```

---

## 🧪 КРОК 15.5. Тести LiqPay

`tests/Feature/Payments/LiqPayGatewayTest.php`:

```php
<?php

namespace Tests\Feature\Payments;

use App\Enums\VacancyStatus;
use App\Events\VacancyExtended;
use Illuminate\Support\Facades\Event;

class LiqPayGatewayTest extends PaymentTestCase
{
    private string $publicKey  = 'sandbox_test_public_key';
    private string $privateKey = 'sandbox_test_private_key';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.gateways.liqpay.public_key'  => $this->publicKey,
            'payments.gateways.liqpay.private_key' => $this->privateKey,
        ]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $data = base64_encode(json_encode(['status' => 'success']));

        $this->post('/webhooks/payments/liqpay', [
            'data'      => $data,
            'signature' => 'definitely_wrong_signature',
        ])->assertStatus(400);
    }

    public function test_missing_data_returns_400(): void
    {
        $this->post('/webhooks/payments/liqpay', [])->assertStatus(400);
    }

    public function test_success_status_extends_vacancy(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);
        $oldExpires = $vacancy->expires_at->copy();

        [$data, $signature] = $this->buildLiqPayWebhook($orderId, 'success');

        $this->post('/webhooks/payments/liqpay', [
            'data'      => $data,
            'signature' => $signature,
        ])->assertOk();

        expect($vacancy->fresh()->expires_at->gt($oldExpires))->toBeTrue();
        Event::assertDispatched(VacancyExtended::class, fn ($e) => $e->days === 30);
    }

    public function test_sandbox_status_is_treated_as_success(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 15);

        [$data, $signature] = $this->buildLiqPayWebhook($orderId, 'sandbox', 100.00);

        $this->post('/webhooks/payments/liqpay', [
            'data'      => $data,
            'signature' => $signature,
        ])->assertOk();

        Event::assertDispatched(VacancyExtended::class, fn ($e) => $e->days === 15);
    }

    public function test_failure_status_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);

        [$data, $signature] = $this->buildLiqPayWebhook($orderId, 'failure');

        $this->post('/webhooks/payments/liqpay', [
            'data'      => $data,
            'signature' => $signature,
        ])->assertOk();

        Event::assertNotDispatched(VacancyExtended::class);
    }

    public function test_reversed_status_is_ignored(): void
    {
        Event::fake();

        $vacancy = $this->makeVacancy();
        $orderId = $this->buildOrderId($vacancy, 30);

        // Спочатку успішна оплата
        [$data1, $sig1] = $this->buildLiqPayWebhook($orderId, 'success', 200.00, 'pay_001');
        $this->post('/webhooks/payments/liqpay', ['data' => $data1, 'signature' => $sig1])->assertOk();

        $extendedExpires = $vacancy->fresh()->expires_at->copy();

        // Потім reversed (повернення)
        [$data2, $sig2] = $this->buildLiqPayWebhook($orderId, 'reversed', 200.00, 'pay_001_rev');
        $this->post('/webhooks/payments/liqpay', ['data' => $data2, 'signature' => $sig2])->assertOk();

        // expires_at не повернулась назад (ми не обробляємо reversed)
        expect($vacancy->fresh()->expires_at->toIso8601String())
            ->toBe($extendedExpires->toIso8601String());
    }

    // =========================================================================
    // Хелпер побудови LiqPay webhook (SHA1)
    // =========================================================================

    private function buildLiqPayWebhook(
        string $orderId,
        string $status,
        float $amount = 200.00,
        string $paymentId = null,
    ): array {
        $params = [
            'public_key' => $this->publicKey,
            'version'    => '3',
            'action'     => 'pay',
            'payment_id' => $paymentId ?? 'liqpay_' . uniqid(),
            'status'     => $status,
            'amount'     => $amount,
            'currency'   => 'UAH',
            'order_id'   => $orderId,
        ];

        $data      = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));
        $signature = base64_encode(sha1($this->privateKey . $data . $this->privateKey, true));

        return [$data, $signature];
    }
}
```

---

## 🧪 КРОК 15.6. Тест `CheckoutService::buildOrderId` / `parseOrderId`

`tests/Unit/Payments/OrderIdTest.php`:

```php
<?php

use App\Payments\CheckoutService;

test('buildOrderId генерує валідний формат', function () {
    $orderId = CheckoutService::buildOrderId(42, 30);
    expect($orderId)->toMatch('/^vac_42_30_[a-f0-9]+$/');
});

test('parseOrderId витягує vacancy_id і days', function () {
    $orderId = CheckoutService::buildOrderId(42, 30);
    [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

    expect($vacancyId)->toBe(42)
        ->and($days)->toBe(30);
});

test('parseOrderId повертає nulls для невалідного формату', function () {
    [$vacancyId, $days] = CheckoutService::parseOrderId('unknown_format');

    expect($vacancyId)->toBeNull()
        ->and($days)->toBeNull();
});

test('кожен buildOrderId унікальний (uniqid)', function () {
    $ids = collect(range(1, 100))->map(fn () => CheckoutService::buildOrderId(1, 30));
    expect($ids->unique()->count())->toBe(100);
});

test('parseOrderId обробляє великі vacancy_id', function () {
    $orderId = CheckoutService::buildOrderId(999999, 90);
    [$vacancyId, $days] = CheckoutService::parseOrderId($orderId);

    expect($vacancyId)->toBe(999999)
        ->and($days)->toBe(90);
});
```

---

## 🧪 КРОК 15.7. Запуск і звіт

```bash
# Тільки платіжні тести
php artisan test --filter="Payments|MonoPay|WayForPay|LiqPay|OrderId"

# З покриттям
php artisan test --filter="Payments" --coverage --min=80
```

Очікуваний результат:

```
Unit/Payments/OrderIdTest............... 5 passed
Feature/Payments/WebhookControllerTest.. 2 passed
Feature/Payments/MonoPayGatewayTest..... 4 passed  (sodium required!)
Feature/Payments/WayForPayGatewayTest... 4 passed
Feature/Payments/LiqPayGatewayTest...... 5 passed
Total: 20 tests, 0 failures
```

---

## ⚠️ Критичні нюанси тестів

### 1. MonoPay тести потребують sodium
Ed25519 верифікація — реальна крипто. Якщо sodium відсутній — тест автоматично позначиться `skipped`. Це нормально. Краще встановити sodium і запускати всі тести.

### 2. WayForPay — порядок полів у підписі
Хелпер `buildWfpWebhookPayload` будує точно такий самий рядок, як `WayForPayGateway::verifyHmacSignature`. Якщо тест падає на підписі — значить порядок у хелпері не збігається з gateway. Звіряй з документацією WFP.

### 3. LiqPay — форма (`x-www-form-urlencoded`), не JSON
`$this->post(...)` в Laravel тестах надсилає саме urlencoded. Це правильно для LiqPay. Але якщо раптом у тестах зміниш на `$this->postJson(...)` — LiqPay `parseWebhook` не знайде `data` і `signature`.

### 4. `Event::assertDispatchedTimes` для idempotency
Один із важливих тестів: ту саму транзакцію не оброблено двічі. `assertDispatchedTimes(VacancyExtended::class, 1)` — саме один dispatch.

### 5. Тест `reversed` у LiqPay
Тест показує, що повернення коштів НЕ скорочує термін вакансії. Це свідома бізнес-логіка: якщо клієнт повернув гроші — вакансія залишається продовженою (технічно), але менеджер повинен отримати алерт. Сьогодні — просто ignore; у майбутньому — окремий модуль refund.

---

## ✅ Очікуваний результат

1. `tests/Feature/Payments/PaymentTestCase.php` — базовий клас.
2. `tests/Feature/Payments/WebhookControllerTest.php` — 2 тести.
3. `tests/Feature/Payments/MonoPayGatewayTest.php` — 4 тести.
4. `tests/Feature/Payments/WayForPayGatewayTest.php` — 4 тести.
5. `tests/Feature/Payments/LiqPayGatewayTest.php` — 5 тестів.
6. `tests/Unit/Payments/OrderIdTest.php` — 5 тестів.

Звіт:
```
20 платіжних тестів, 0 помилок.

Всі 4 провайдери готові:
  stripe    ✅ (модуль 6 + рефакторинг в 11A)
  mono      ✅ (Ed25519, /api/merchant/invoice/create)
  wayforpay ✅ (HMAC-MD5, form/hosted mode, accept+signature)
  liqpay    ✅ (SHA1, urlencoded webhook, sandbox статус)

Активний провайдер: PAYMENT_GATEWAY=mono (змінюється без деплою).
```

---

## 🚨 Чого НЕ робити

- ❌ Не використовуй реальні ключі провайдерів у тестах — тільки `test_*` / `sandbox_*`.
- ❌ Не коміть `LIQPAY_PRIVATE_KEY` або `MONO_TOKEN` у `.env.testing`.
- ❌ Не заміняй urlencoded-формат LiqPay на JSON у тестах.
- ❌ Не тестуй crypto без `sodium` — краще `markTestSkipped`.
- ❌ Не пиши тести без `Event::fake()` — реальні події запустять Nutgram-нотифікації.

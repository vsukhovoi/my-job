# Claude Code Prompt — Telegram Bot Roadmap Фаза 1

**Проєкт:** My Job (myjob.co.ua) — Laravel 13.4 + PHP 8.3
**Модуль:** Telegram Bot Infrastructure
**Фаза:** 1 (Закриття TODO + фундамент)
**Підфази:** 1.1 + 1.2 + 1.3 (виконуються в зазначеному порядку)
**Принцип:** Поетапна реалізація з reconnaissance-кроком, явні stop-gates між підфазами, повне покриття тестами.

---

## 🛑 КРИТИЧНІ ПРАВИЛА (не порушувати під жодних умов)

1. **Volt-only компоненти** — жодних стандартних Livewire компонентів
2. **PHPUnit 12** з `#[Test]` атрибутами — **жодних docblock анотацій** (`/** @test */`)
3. **`actingAs()` ВИКЛЮЧНО ДО** `Volt::test()` — НЕ ланцюжком (інакше `auth()->user()` поверне null всередині Volt)
4. **Enum для ролей:** `UserRole::Candidate`, `UserRole::Employer` (НЕ string literals)
5. **Enum для статусів:** `ApplicationStatus::Pending` тощо (НЕ string literals)
6. **`user_id`** як foreign key для кандидатів (НЕ `seeker_id`)
7. **Stripe — НЕ чіпати** (permanent stub)
8. **Не переписувати** існуючу логіку — тільки розширювати
9. **Юридичний копірайтинг:** ніяких слів, що натякають на посередництво у працевлаштуванні (clause 2.2 оферти)
10. **Заборонено** робити `extend` стилем заміщення — тільки доповнення / нові методи

---

## 🔍 КРОК 1 — Reconnaissance (READ-ONLY, нічого не змінювати)

Перед будь-якими змінами прочитати та зрозуміти існуючий код. **НЕ ПИСАТИ КОД на цьому етапі.**

### 1.1. Структура та конвенції
Прочитати:
- `composer.json` — підтвердити версії Laravel/PHP/PHPUnit
- `phpunit.xml` — налаштування тестів
- `config/services.php` — секція `telegram`
- `app/Providers/EventServiceProvider.php` — як реєструються listener'и
- `app/Providers/AppServiceProvider.php` — реєстрація сервісів

### 1.2. Telegram-інфраструктура (поточний стан)
Прочитати **всі** файли:
- `app/Services/TelegramNotifier.php` — поточний інтерфейс/реалізація
- `app/Services/TelegramAuthService.php` — як працює авторизація
- `app/Http/Controllers/TelegramAuthController.php` — endpoints
- `app/Notifications/Channels/TelegramChannel.php` (якщо існує)
- `routes/web.php` і `routes/api.php` — поточні telegram-маршрути
- `app/Models/User.php` — методи `prefersEmail()`, `prefersTelegram()`, `telegram_id`, `notification_channel`

### 1.3. Existing notification listeners (як референс патерну)
Прочитати **всі існуючі** listener'и Telegram-сповіщень для розуміння patterns:
- Listener'и для `ApplicationCreated` (нова заявка → роботодавцю)
- Listener'и для `ApplicationStatusChanged` (статус → кандидату)
- Команду `SendVacancyAlerts`
- Команду `vacancies:refresh-anonymous`
- Будь-який inheritance/interface, який вони використовують

### 1.4. Async Interviews модуль (готова частина)
Прочитати:
- `app/Enums/InterviewRequestStatus.php`
- `app/Models/InterviewRequest.php`
- `app/Models/InterviewResponse.php`
- `app/Events/InterviewRequestSent.php`
- `app/Events/InterviewResponseSubmitted.php`
- `app/Services/AsyncInterviewService.php`
- `app/Console/Commands/MarkExpiredInterviews.php`
- `app/Filament/Resources/InterviewRequestResource.php`
- Routes пов'язані з async-interview (form/view)
- Volt-компоненти: `request-form`, `response-form`, `response-view`

### 1.5. Тестова інфраструктура
Прочитати:
- `tests/TestCase.php` — базовий клас
- `tests/Feature/` — приклад існуючого feature-test з Telegram-mock
- `tests/CreatesApplication.php` (якщо є)

### 1.6. Python-bot integration (зовнішнє)
Прочитати документацію / коментарі у коді щодо:
- Поточного формату HTTP-виклику на `/send-message` endpoint Python-бота
- Чи приймає Python-бот reply_markup / inline_keyboard зараз
- Як організована обробка webhooks від Telegram (якщо вона є)

### ⏸ STOP-GATE 1
Після reconnaissance вивести **коротке резюме** у форматі:
```
ЗНАЙДЕНО:
- TelegramNotifier API: [список методів]
- Listener patterns: [референс файл]
- Async Interview events: [signature та payload]
- Test patterns: [що використовується для Telegram-mock]

НЕЗРОЗУМІЛО / ПОТРЕБУЄ УТОЧНЕННЯ:
- [...]
```

**Зупинитись і чекати підтвердження** перед переходом до КРОКУ 2. Не починати implementation без явного "go".

---

## 🛠 КРОК 2 — Підфаза 1.1: Listener'и асинхронних співбесід

### 2.1. Створити `NotifyInterviewRequestSent` listener
**Файл:** `app/Listeners/NotifyInterviewRequestSent.php`

**Логіка:**
- Слухає `InterviewRequestSent` event
- Адресат: **кандидат** (`InterviewRequest->candidate` або `->user`)
- Перевірка: `$candidate->telegram_id` існує + `$candidate->prefersTelegram()`
- Якщо умови НЕ виконані → fallback на email (через існуючий механізм) АБО просто skip (узгодити з існуючим патерном з reconnaissance)
- Якщо умови виконані → виклик `TelegramNotifier` з повідомленням + кнопкою «Відповісти»
- Текст повідомлення (UA):
  ```
  📨 Запит на співбесіду
  
  Компанія {company_name} запрошує вас пройти асинхронну співбесіду на вакансію «{vacancy_title}».
  
  ⏰ Термін відповіді: до {expires_at}
  📝 Кількість питань: {questions_count}
  ```
- Кнопка: `[📝 Відповісти на питання]` — deep link на `route('interview.respond', $request->id)`

### 2.2. Створити `NotifyInterviewResponseSubmitted` listener
**Файл:** `app/Listeners/NotifyInterviewResponseSubmitted.php`

**Логіка:**
- Слухає `InterviewResponseSubmitted` event
- Адресат: **роботодавець** (через `InterviewRequest->vacancy->user` або `->employer`)
- Перевірка: `$employer->telegram_id` + `$employer->prefersTelegram()`
- Текст повідомлення (UA):
  ```
  ✅ Кандидат відповів на співбесіду
  
  {candidate_name} надіслав(ла) відповіді на питання асинхронної співбесіди для вакансії «{vacancy_title}».
  ```
- Кнопка: `[👀 Переглянути відповіді]` — deep link на `route('interview.view', $response->id)`

### 2.3. Зареєструвати listener'и в `EventServiceProvider`
Додати в `$listen`:
```php
InterviewRequestSent::class => [
    NotifyInterviewRequestSent::class,
],
InterviewResponseSubmitted::class => [
    NotifyInterviewResponseSubmitted::class,
],
```
**УВАГА:** перевірити, чи не зареєстровано вже (можливі дублі — не створювати).

### 2.4. Тести Підфази 1.1
**Файл:** `tests/Feature/Notifications/InterviewNotificationsTest.php`

Створити **6 тестів** з `#[Test]` атрибутами:

1. `it_sends_telegram_notification_when_interview_request_sent_and_candidate_prefers_telegram`
2. `it_falls_back_to_email_when_candidate_has_no_telegram_id` (або skip — узгодити)
3. `it_skips_telegram_when_candidate_prefers_email`
4. `it_sends_telegram_notification_when_interview_response_submitted_and_employer_prefers_telegram`
5. `it_skips_telegram_notification_when_employer_has_no_telegram_id`
6. `it_includes_deep_link_to_response_view_in_notification_payload`

**Кожен тест** обов'язково:
- Використовує `actingAs()` ДО `Volt::test()` (якщо взагалі застосовується Volt — у listener-тестах радше mock'и)
- Mock'ає Telegram API через Http::fake() або mock на TelegramNotifier
- Перевіряє: правильність payload, наявність кнопки, текст повідомлення (UA-копірайтинг)
- Використовує Enum-based ролі/статуси
- Очікувані статуси: `InterviewRequestStatus::Pending` тощо

### ⏸ STOP-GATE 2
Запустити **тільки** ці тести: `php artisan test --filter=InterviewNotificationsTest`.
Очікуваний результат: **6/6 PASS, zero regressions**.
Зупинитись і повідомити результат. Чекати підтвердження перед переходом до КРОКУ 3.

---

## 🛠 КРОК 3 — Підфаза 1.2: Розширення `TelegramNotifier`

### 3.1. Додати методи до `TelegramNotifier`
**УВАГА:** НЕ переписувати існуючу логіку. Тільки **додавати нові методи** до сервісу.

Нові методи:

#### `sendMessageWithKeyboard(int $telegramId, string $text, array $inlineKeyboard): TelegramMessageResult`
- Розширення поточного `sendMessage`
- Приймає масив inline_keyboard у форматі Telegram Bot API
- Підтримка ParseMode (HTML/MarkdownV2 — узгодити з тим, що використовується)
- Повертає DTO/Value Object з результатом (включаючи `message_id` для подальшого `editMessage`)

#### `editMessageText(int $telegramId, int $messageId, string $newText, ?array $inlineKeyboard = null): bool`
- Виклик `editMessageText` Telegram Bot API через Python-бот endpoint
- Опційне оновлення keyboard
- Безпечна обробка помилок (повідомлення видалене / занадто давнє / однакове)

#### `answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool`
- Підтвердження отримання callback (обов'язково для UX — інакше у користувача "годинник")
- Опційне показу toast/alert

### 3.2. DTO для результатів
**Файл:** `app/DataTransferObjects/TelegramMessageResult.php`
Поля: `bool $success, ?int $messageId, ?int $chatId, ?string $error`

### 3.3. Розширення Python-бота (документація tasks)
**Файл:** `docs/telegram-bot/python-bot-extensions.md` (новий)

Описати **точно** які нові endpoints потрібно реалізувати в Python-боті:
- `POST /send-message-with-keyboard` (input/output JSON-схема)
- `POST /edit-message` (input/output JSON-схема)
- `POST /answer-callback` (input/output JSON-схема)
- `POST /webhook/callback` (від Python-бота назад у Laravel — для callback queries; буде використовуватись у Підфазі 1.3)

**УВАГА:** цей крок — **тільки документація** для подальшої реалізації в Python-боті. Не змінювати Python-код.

### 3.4. Тести Підфази 1.2
**Файл:** `tests/Feature/Services/TelegramNotifierExtensionsTest.php`

Створити **5 тестів**:

1. `it_sends_message_with_inline_keyboard_payload`
2. `it_returns_message_id_for_later_editing`
3. `it_edits_existing_message_text`
4. `it_gracefully_handles_telegram_api_errors`
5. `it_answers_callback_query_with_optional_alert`

**Мок** HTTP-виклики через `Http::fake()`. Перевіряти, що до Python-bot endpoint іде правильний payload.

### ⏸ STOP-GATE 3
Запустити: `php artisan test --filter=TelegramNotifierExtensionsTest`.
Очікуваний результат: **5/5 PASS, zero regressions** на існуючому `TelegramNotifier` тестах.
Зупинитись і чекати підтвердження.

---

## 🛠 КРОК 4 — Підфаза 1.3: CallbackRouter + HMAC signature

### 4.1. HMAC-helper для callback_data
**Файл:** `app/Services/Telegram/CallbackDataSigner.php`

Методи:
- `sign(string $action, string $resourceType, int $resourceId, ?string $param = null): string`
  - Формат: `{action}:{resourceType}:{resourceId}:{param}:{signature}`
  - Signature: перші 8 байт base64-encoded HMAC-SHA256 з `APP_KEY` або окремого `TELEGRAM_CALLBACK_SECRET`
  - **Обмеження Telegram callback_data — 64 байти.** Перевірити що формат вкладається.

- `verify(string $callbackData): ?CallbackDataPayload`
  - Парсить рядок
  - Перевіряє HMAC
  - Повертає DTO або `null` (НЕ кидає виключення — повернути null, дозволити router залогувати)

**Файл:** `app/DataTransferObjects/CallbackDataPayload.php`
Поля: `string $action, string $resourceType, int $resourceId, ?string $param`

**Конфіг:** `config/services.php` — додати `telegram.callback_secret` (читає з `.env`)

### 4.2. Інтерфейс для handlers
**Файл:** `app/Contracts/TelegramCallbackHandlerInterface.php`

```php
interface TelegramCallbackHandlerInterface
{
    public function canHandle(CallbackDataPayload $payload): bool;
    public function handle(CallbackDataPayload $payload, User $user, int $messageId): void;
}
```

### 4.3. Router-сервіс
**Файл:** `app/Services/Telegram/TelegramCallbackRouter.php`

- Constructor injection масиву handlers (через tagged services)
- Метод `dispatch(string $callbackData, User $user, int $messageId): void`:
  1. `CallbackDataSigner::verify()` → якщо null → залогувати + answerCallbackQuery з "Недійсний запит" → return
  2. Знайти handler через `canHandle()`
  3. Викликати `handle()`
  4. Залогувати результат (success/exception)
  5. Завжди викликати `answerCallbackQuery` (інакше UX зламається)

### 4.4. Webhook endpoint
**Файл:** `app/Http/Controllers/Api/TelegramWebhookController.php`

- `POST /api/telegram/webhook/callback`
- **Захист:** middleware-перевірка секретного заголовка від Python-бота (`X-Telegram-Webhook-Token` = `config('services.telegram.webhook_token')`)
- Парсить payload від Python-бота:
  ```json
  {
    "telegram_user_id": 123,
    "callback_data": "...",
    "message_id": 456,
    "callback_query_id": "..."
  }
  ```
- Знаходить `User` за `telegram_id` (HMAC уже забезпечив автентичність callback_data, але прив'язка до користувача потрібна)
- Викликає `TelegramCallbackRouter::dispatch()`
- Повертає 200 OK / 4xx з логом

**Маршрут:** додати в `routes/api.php` з обмеженням rate limit (60/min на IP).

### 4.5. Rate limiting на callback queries
Реалізувати через Redis:
- Ключ: `telegram:callback:rate:{user_id}`
- Ліміт: 30 callbacks / minute per user
- При перевищенні: `answerCallbackQuery` з "Забагато запитів. Спробуйте за хвилину" + НЕ виконувати handler

### 4.6. Тести Підфази 1.3
**Файл:** `tests/Feature/Services/TelegramCallbackRouterTest.php`

Створити **7 тестів**:

1. `it_signs_and_verifies_callback_data_successfully`
2. `it_rejects_tampered_callback_data` (підмінити signature)
3. `it_rejects_callback_data_exceeding_64_bytes` (Telegram limit)
4. `it_dispatches_to_correct_handler_based_on_action`
5. `it_logs_and_returns_safely_when_no_handler_found`
6. `it_enforces_rate_limit_on_callback_queries`
7. `it_rejects_webhook_without_valid_token_header`

### 4.7. Документація для розробників
**Файл:** `docs/telegram-bot/callback-protocol.md` (новий)

Описати:
- Формат callback_data
- Як додати новий handler (приклад step-by-step)
- Як працює HMAC підпис
- Rate limiting правила
- Webhook security model

### ⏸ STOP-GATE 4
Запустити: `php artisan test --filter=TelegramCallbackRouterTest`.
Очікуваний результат: **7/7 PASS, zero regressions**.

---

## ✅ КРОК 5 — Фінальна валідація

### 5.1. Full test suite
Запустити **повний** test suite:
```bash
php artisan test
```
Очікуваний результат: **усі тести PASS, zero regressions**.

### 5.2. Підсумковий звіт
Вивести у форматі:
```
ФАЗА 1 — ЗАВЕРШЕНО

Підфаза 1.1 (Listener'и співбесід): 6/6 PASS
Підфаза 1.2 (TelegramNotifier розширення): 5/5 PASS
Підфаза 1.3 (CallbackRouter + HMAC): 7/7 PASS
ВСЬОГО НОВИХ ТЕСТІВ: 18/18 PASS
ПОВНИЙ TEST SUITE: [X]/[X] PASS
ZERO REGRESSIONS: ✓

СТВОРЕНІ ФАЙЛИ:
- app/Listeners/NotifyInterviewRequestSent.php
- app/Listeners/NotifyInterviewResponseSubmitted.php
- app/Services/Telegram/CallbackDataSigner.php
- app/Services/Telegram/TelegramCallbackRouter.php
- app/DataTransferObjects/CallbackDataPayload.php
- app/DataTransferObjects/TelegramMessageResult.php
- app/Contracts/TelegramCallbackHandlerInterface.php
- app/Http/Controllers/Api/TelegramWebhookController.php
- docs/telegram-bot/python-bot-extensions.md
- docs/telegram-bot/callback-protocol.md
- tests/Feature/Notifications/InterviewNotificationsTest.php
- tests/Feature/Services/TelegramNotifierExtensionsTest.php
- tests/Feature/Services/TelegramCallbackRouterTest.php

ОНОВЛЕНІ ФАЙЛИ:
- app/Services/TelegramNotifier.php (додано методи)
- app/Providers/EventServiceProvider.php (зареєстровані listeners)
- config/services.php (додано telegram.callback_secret, telegram.webhook_token)
- routes/api.php (додано webhook маршрут)

.ENV ЗМІННІ ДЛЯ ПРОДАКШНУ:
- TELEGRAM_CALLBACK_SECRET=...
- TELEGRAM_WEBHOOK_TOKEN=...

TODO ДЛЯ PYTHON-БОТА (поза скоупом Laravel-частини):
- Реалізувати endpoints: /send-message-with-keyboard, /edit-message, /answer-callback
- Реалізувати webhook POST на Laravel: /api/telegram/webhook/callback
- Деталі: docs/telegram-bot/python-bot-extensions.md
```

### 5.3. Git workflow
**НЕ створювати** commit/PR автоматично. Очікувати інструкцій від користувача (раніше використовувалась схема: окрема гілка → manual review → merge до main).

---

## 📋 Чекліст відповідності project conventions

- [ ] Усі тести використовують `#[Test]` атрибути (НЕ docblock)
- [ ] `actingAs()` викликається **до** `Volt::test()` (де застосовується)
- [ ] Volt-only компоненти (НЕ standard Livewire)
- [ ] Enum'и замість string literals (роли, статуси)
- [ ] `user_id` як FK (НЕ `seeker_id`)
- [ ] Stripe не чіпається
- [ ] UA-копірайтинг без слів про посередництво
- [ ] Існуюча логіка не переписана — тільки розширення
- [ ] Zero regressions у повному test suite

---

## 🚫 Що НЕ робити в цьому промпті

- НЕ реалізовувати Python-bot endpoints (тільки документація)
- НЕ створювати конкретних callback handlers (Підфаза 2.x і далі)
- НЕ міняти UI (Volt-компоненти не зачіпаються)
- НЕ створювати Filament-ресурсів
- НЕ робити migrations (на цій фазі нові таблиці НЕ потрібні)
- НЕ міняти `.env` напряму — тільки `.env.example` з placeholder'ами та `config/services.php`

---

**Готовність до виконання:** Прочитати весь промпт. Розпочати з КРОКУ 1 (Reconnaissance). Чекати підтвердження на кожному STOP-GATE.

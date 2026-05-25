# Claude Code Prompt — Phase 3.1: Interactive Application Notifications

**Проєкт:** My Job (myjob.co.ua)
**Тип:** Розширення існуючого функціоналу (Phase 3.1 з roadmap)
**Передумова:** Phase 1 + 1B завершені, бот деплоєний на bot.myjob.co.ua, callback infrastructure готова
**Скоп:** Перетворити просте текстове сповіщення «нова заявка» на інтерактивне повідомлення з 4 кнопками

---

## Контекст

Зараз роботодавець отримує текстове сповіщення про нову заявку без можливості діяти прямо з Telegram — мусить переходити на сайт. Phase 3.1 додає 4 inline-кнопки, які покривають **~80% денних рутинних дій** роботодавця по заявках:

**Цільовий вигляд повідомлення:**
```
📨 Нова заявка на вашу вакансію

Кандидат: Іван Петренко
Вакансія: Senior PHP Developer
Досвід: 5 років

[📄 Переглянути CV]  [✅ Запросити на співбесіду]
[❌ Відхилити]       [💬 Написати]
```

Це **перший справжній use case** інфраструктури `TelegramCallbackRouter` + `CallbackDataSigner`, яку ми побудували у Phase 1.

---

## 🛑 КРИТИЧНІ ПРАВИЛА

1. **Project conventions** (всі обов'язкові):
   - `#[Test]` атрибути в тестах, НЕ docblock
   - `actingAs()` ДО `Volt::test()` (не ланцюжком)
   - Volt-only компоненти (НЕ standard Livewire)
   - Enum для ролей: `UserRole::Candidate`, `UserRole::Employer`
   - Enum для статусів: `ApplicationStatus::*`
   - `user_id` як FK (НЕ `seeker_id`)
   - Stripe — НЕ чіпати
2. **UA-копірайтинг:** усі тексти українською, без слів про "посередництво у працевлаштуванні"
3. **НЕ переписувати** існуючу логіку — тільки **розширювати** через нові методи/handler'и
4. **Bearer token + chat_id int** — це production fixes з 22.05.2026. Кожен новий тест, що перевіряє `TelegramNotifier` виклик, ОБОВ'ЯЗКОВО перевіряє: (a) заголовок `Authorization: Bearer ...`, (b) `is_int($payload['chat_id'])`. Це regression coverage щоб баги не повернулися
5. **HMAC формат callback_data:** `action:resource_type:resource_id:param:signature`. Telegram має ліміт 64 байти — перевіряти кожен новий тип callback на влізаність
6. **One callback handler = одна відповідальність.** НЕ створювати handler'ів які роблять багато різних дій
7. **Завжди викликати `answerCallbackQuery`** — інакше у користувача "годинник". Це робиться у `TelegramCallbackRouter`, тому handler'и про це не дбають (перевірити)

---

## 🔍 КРОК 1 — Reconnaissance (READ-ONLY)

### 1.1. Існуюче сповіщення про нову заявку
Знайти та прочитати:
- Listener, який слухає `ApplicationCreated` (або як він називається) і шле в Telegram
- Поточний формат повідомлення (текст)
- `Application` model — поля, relationships (`vacancy`, `user`/`candidate`)
- `ApplicationStatus` Enum — усі значення (зокрема: чи є `InterviewInvited`, `Rejected`, `Pending` тощо — точні назви)
- `Vacancy` model — relationships (`user`/`employer`, `company`)
- `User` model — relationships для кандидата (`resume`?), `telegram_id`, `prefersTelegram()`

### 1.2. Callback infrastructure (готова з Phase 1)
- `app/Services/Telegram/CallbackDataSigner.php` — формат `sign()`/`verify()`
- `app/Services/Telegram/TelegramCallbackRouter.php` — як реєструються handler'и
- `app/Contracts/TelegramCallbackHandlerInterface.php` — сигнатура
- `app/DataTransferObjects/CallbackDataPayload.php` — поля
- `app/Services/TelegramNotifier.php` — `sendMessageWithKeyboard`, `editMessageText`, `answerCallbackQuery`

### 1.3. Route patterns
- `routes/web.php` — як названі існуючі employer-маршрути для applications
- Чи існує маршрут типу `/employer/applications/{id}/cv` для перегляду CV кандидата?
- Чи існує chat-маршрут для переписки employer↔candidate?

### 1.4. Existing notifications candidate отримує
Прочитати листенер'и, які шлють сповіщення кандидату при зміні `ApplicationStatus`:
- На rejection — який текст? Це для template-узгодження
- На interview invite — як зараз працює (якщо взагалі є)

### 1.5. ProfileCompletenessService та resumes
- `Resume` model — поля, які потрібні для повідомлення (досвід, ім'я)
- Чи всі кандидати мають Resume? Чи може бути null?

### ⏸ STOP-GATE 1
Вивести резюме у форматі:
```
EXISTING NOTIFICATION:
- Listener: app/Listeners/[NAME].php
- Triggered by: [Event]
- Current text format: "[приклад]"
- TelegramNotifier method used: sendMessage

APPLICATION STATUS ENUM:
- Pending, Reviewing, InterviewInvited, Rejected, ... (точні назви cases)

CANDIDATE DATA AVAILABLE:
- $application->user->name (string)
- $application->user->resume->experience_years (?int)
- $application->user->resume (може бути null?)

ROUTES:
- /employer/applications/{id}/cv → [Volt-route назва] OR не існує
- /employer/chat/{user_id} → [назва] OR не існує

CHAT EXISTS: Yes/No, де знаходиться

CALLBACK PAYLOAD SIZE TEST:
- Найдовший action: "invite_interview" (16 chars)
- Resource type: "application" (11 chars)
- ID (assume 9-digit): 999999999
- Signature: 8 bytes base64 = ~11 chars
- TOTAL: ~50 bytes → ВЛІЗАЄ у 64-байтний ліміт Telegram ✓
```

**Зупинитись.** Чекати "go".

---

## 🛠 КРОК 2 — Callback handlers (4 штуки)

### 2.1. Створити базовий abstract handler

**Файл:** `app/Services/Telegram/Handlers/AbstractApplicationCallbackHandler.php`

```php
abstract class AbstractApplicationCallbackHandler implements TelegramCallbackHandlerInterface
{
    public function __construct(
        protected TelegramNotifier $notifier,
    ) {}
    
    protected function getApplication(int $applicationId): ?Application
    {
        return Application::with(['vacancy', 'user'])->find($applicationId);
    }
    
    protected function ensureEmployerOwnsApplication(Application $application, User $employer): bool
    {
        return $application->vacancy->user_id === $employer->id;
    }
    
    // Утиліти для editMessageText із оновленням кнопок (видалити кнопки, лишити статус)
    protected function clearKeyboardWithStatus(int $chatId, int $messageId, string $statusLine): void
    {
        // Витягнути оригінальний текст з контексту АБО реконструювати з Application
        // ... (логіку обирати залежно від reconnaissance)
    }
}
```

**Resource type для всіх 4-х:** `application`.

### 2.2. `RejectApplicationHandler`
**Файл:** `app/Services/Telegram/Handlers/RejectApplicationHandler.php`

**`canHandle()`:** `$payload->action === 'reject' && $payload->resourceType === 'application'`

**`handle()`:**
1. Знайти Application через `getApplication($payload->resourceId)` → якщо null → return (RouterShellAlready answer)
2. Перевірити володіння через `ensureEmployerOwnsApplication()` → якщо false → return
3. Перевірити поточний статус — якщо вже rejected/завершений, return (idempotency)
4. Викликати `$application->update(['status' => ApplicationStatus::Rejected])` (точна назва з reconnaissance)
5. **Запустити event** `ApplicationStatusChanged` — це триггерить існуючу логіку, яка вже шле сповіщення кандидату. **НЕ дублювати** код сповіщення кандидату — використати існуючий механізм
6. `editMessageText` повідомлення роботодавцю: оригінальний текст + "\n\n❌ Відхилено о {datetime}"
7. Прибрати кнопки повністю (`inline_keyboard: []`)

**УВАГА — template повідомлення кандидату:**
Перевірити в reconnaissance: чи існує `lang/uk/notifications.php` або подібний для текстів повідомлень. Якщо так — додати ключ `applications.rejected_by_employer`:
```php
'rejected_by_employer' => 'На жаль, ваша заявка на вакансію «:vacancy_title» розглянута і не пройшла далі. Не засмучуйтесь — продовжуйте подавати на інші вакансії!'
```
Якщо механізму lang-файлів для notifications немає — hard-code у листенері, що шле кандидату.

### 2.3. `InviteToInterviewHandler`
**Файл:** `app/Services/Telegram/Handlers/InviteToInterviewHandler.php`

**`canHandle()`:** `$payload->action === 'invite' && $payload->resourceType === 'application'`

**`handle()`:**
1. Знайти + перевірити володіння (як у Reject)
2. Перевірити поточний статус — якщо вже InterviewInvited/Rejected — return
3. `$application->update(['status' => ApplicationStatus::InterviewInvited])` (точна назва)
4. Event `ApplicationStatusChanged` → існуюча логіка → кандидат отримує своє сповіщення
5. `editMessageText` роботодавцю: оригінальний текст + "\n\n✅ Запрошено на співбесіду о {datetime}"
6. Прибрати кнопки

**Template для кандидата (через listener на ApplicationStatusChanged):**
```php
'interview_invited' => 'Чудові новини! Роботодавець запросив вас на співбесіду на вакансію «:vacancy_title». Очікуйте контакту найближчим часом.'
```

**УВАГА:** оскільки preset questions немає у Vacancy (підтверджено), це **простий статус-change**, не Phase 1 async interview flow.

### 2.4. **БЕЗ handler'ів** для `view_cv` та `write`

Це **url buttons**, не callback. Telegram сам відкриває URL без виклику webhook'у. Не потребують callback_data + HMAC + handler.

**Створити URL helpers:**

**Файл:** `app/Services/Telegram/UrlGenerators/ApplicationUrlGenerator.php`

```php
class ApplicationUrlGenerator
{
    public function cvViewUrl(Application $application): string
    {
        return route('employer.applications.cv', ['application' => $application->id]);
        // АБО точна назва маршруту з reconnaissance
    }
    
    public function chatUrl(Application $application): string
    {
        return route('employer.chat.with', ['user' => $application->user_id]);
        // АБО точна назва маршруту з reconnaissance
    }
}
```

**Якщо такого маршруту немає для CV** — НЕ створювати новий контролер. Натомість використати URL до сторінки заявки (employer.applications.show), де CV вбудоване через Volt-компонент. Уточнити у reconnaissance.

### 2.5. Реєстрація handler'ів
**Файл:** `app/Providers/AppServiceProvider.php` (або де реєструються Telegram-сервіси)

```php
$this->app->tag([
    RejectApplicationHandler::class,
    InviteToInterviewHandler::class,
], 'telegram.callback.handlers');
```

`TelegramCallbackRouter` має collect tagged services у constructor (перевірити в reconnaissance — можливо вже реалізовано).

### ⏸ STOP-GATE 2
Після написання handler'ів + URL generator, **БЕЗ тестів та оновлення Listener'а**:
- Вивести список створених файлів
- Вивести `canHandle()` логіку кожного handler'а
- Вивести як саме `editMessageText` оновлює UI після кліку
- Чекати "go" перед КРОКОМ 3

---

## 🛠 КРОК 3 — Оновити Listener для нового формату повідомлення

### 3.1. Знайти існуючий listener
З reconnaissance — назва файлу (наприклад `NotifyEmployerOfNewApplication.php`).

### 3.2. Розширити, НЕ переписати
**УВАГА:** не видаляти fallback на email і поточний flow. Тільки **замінити** виклик `sendMessage` на `sendMessageWithKeyboard`.

**Псевдокод:**
```php
public function handle(ApplicationCreated $event): void
{
    $employer = $event->application->vacancy->user;
    
    if (!$employer->telegram_id || !$employer->prefersTelegram()) {
        // existing email/fallback flow — НЕ ЧІПАТИ
        return;
    }
    
    $text = $this->formatMessage($event->application);
    $keyboard = $this->buildKeyboard($event->application);
    
    $this->notifier->sendMessageWithKeyboard(
        chatId: $employer->telegram_id,  // ВЖЕ int
        text: $text,
        parseMode: 'HTML',  // або як уже використовується
        inlineKeyboard: $keyboard,
    );
}

private function formatMessage(Application $application): string
{
    $candidate = $application->user;
    $vacancy = $application->vacancy;
    $experience = $candidate->resume?->experience_years;
    
    return sprintf(
        "📨 <b>Нова заявка на вашу вакансію</b>\n\n" .
        "Кандидат: %s\n" .
        "Вакансія: %s\n" .
        "Досвід: %s",
        e($candidate->name),
        e($vacancy->title),
        $experience ? "{$experience} років" : 'не вказано',
    );
}

private function buildKeyboard(Application $application): array
{
    $signer = app(CallbackDataSigner::class);
    $urls = app(ApplicationUrlGenerator::class);
    
    return [
        [
            ['text' => '📄 Переглянути CV', 'url' => $urls->cvViewUrl($application)],
            ['text' => '✅ Запросити на співбесіду', 'callback_data' => $signer->sign('invite', 'application', $application->id)],
        ],
        [
            ['text' => '❌ Відхилити', 'callback_data' => $signer->sign('reject', 'application', $application->id)],
            ['text' => '💬 Написати', 'url' => $urls->chatUrl($application)],
        ],
    ];
}
```

### 3.3. Edge cases
- **Якщо у вакансії немає кандидатової resume** → "Досвід: не вказано"
- **Якщо `$candidate->name` містить HTML-спецсимволи** → екранування `e()`
- **Якщо `$vacancy->title` довгий** → не обрізати на цьому етапі (Telegram сам обрізає за ~4096 char ліміт повідомлення)

---

## 🧪 КРОК 4 — Тести

**Файл 1:** `tests/Feature/Notifications/NewApplicationNotificationWithKeyboardTest.php`

**5 тестів:**

1. `it_sends_message_with_4_buttons_when_employer_prefers_telegram`
   - Перевірити що `sendMessageWithKeyboard` викликаний з правильним keyboard structure
   - Перевірити що 2 кнопки мають `url`, 2 мають `callback_data`
2. `it_falls_back_to_existing_flow_when_employer_has_no_telegram_id`
3. `it_passes_chat_id_as_int_to_notifier` (regression #2)
4. `it_uses_bearer_authorization_header_when_calling_python_bot` (regression #1 — через Http::fake() inspection)
5. `it_includes_candidate_name_and_vacancy_title_in_message_text`

**Файл 2:** `tests/Feature/Services/Telegram/Handlers/RejectApplicationHandlerTest.php`

**5 тестів:**

1. `it_can_handle_reject_application_callback`
2. `it_updates_application_status_to_rejected`
3. `it_does_not_act_when_employer_does_not_own_application` (authorization)
4. `it_is_idempotent_when_application_already_rejected`
5. `it_dispatches_application_status_changed_event_for_candidate_notification`

**Файл 3:** `tests/Feature/Services/Telegram/Handlers/InviteToInterviewHandlerTest.php`

**5 тестів** (аналогічно Reject):

1. `it_can_handle_invite_application_callback`
2. `it_updates_application_status_to_interview_invited`
3. `it_does_not_act_when_employer_does_not_own_application`
4. `it_is_idempotent_when_application_already_invited`
5. `it_dispatches_application_status_changed_event_for_candidate_notification`

**Файл 4:** `tests/Feature/Services/Telegram/UrlGenerators/ApplicationUrlGeneratorTest.php`

**3 тести:**

1. `it_generates_cv_view_url_for_employer`
2. `it_generates_chat_url_for_employer_to_candidate`
3. `it_generates_urls_under_telegram_callback_data_limit_when_combined_with_signatures`

**Файл 5:** `tests/Feature/Integration/ApplicationRejectionFlowTest.php` (E2E через TelegramCallbackRouter)

**3 тести:**

1. `it_routes_reject_callback_to_handler_and_updates_application`
2. `it_routes_invite_callback_to_handler_and_updates_application`
3. `it_rejects_tampered_callback_data_for_application_actions` (HMAC integrity)

### ⏸ STOP-GATE 4
Запустити: `php artisan test --filter="ApplicationNotification|RejectApplicationHandler|InviteToInterviewHandler|ApplicationUrlGenerator|ApplicationRejectionFlow"`.
Очікуваний результат: **21/21 PASS**.

Повний test suite: `php artisan test` → **437/437 PASS** (416 + 21), zero regressions.

---

## 🛠 КРОК 5 — UA template для candidate notifications

### 5.1. Знайти існуючі templates
Залежно від reconnaissance — або:
- `lang/uk/notifications.php` (Laravel translations)
- `app/Notifications/...` (Notification class)
- Hard-coded у listenерах

### 5.2. Додати/оновити тексти

**Для статус InterviewInvited:**
```
Чудові новини! Роботодавець запросив вас на співбесіду на вакансію «{назва}». Очікуйте контакту найближчим часом.
```

**Для статус Rejected:**
```
На жаль, ваша заявка на вакансію «{назва}» розглянута і не пройшла далі. Не засмучуйтесь — продовжуйте подавати на інші вакансії!
```

**УВАГА:** ніяких слів, що натякають на посередництво ("ми передали" → "роботодавець розглянув"). Перевірити кожен рядок.

### 5.3. Тест для нових текстів
**Файл:** `tests/Feature/Notifications/CandidateStatusChangedNotificationTest.php`

**2 тести:**

1. `it_sends_correct_text_to_candidate_when_invited_to_interview`
2. `it_sends_correct_text_to_candidate_when_rejected`

---

## ✅ КРОК 6 — Фінальна валідація

### 6.1. Усі тести
```bash
php artisan test
```
Очікуваний результат: **439/439 PASS** (416 + 21 + 2), zero regressions.

### 6.2. Підсумковий звіт
```
═══════════════════════════════════════════
PHASE 3.1 — Interactive Application Notifications
═══════════════════════════════════════════

СТВОРЕНІ ФАЙЛИ:
- app/Services/Telegram/Handlers/AbstractApplicationCallbackHandler.php
- app/Services/Telegram/Handlers/RejectApplicationHandler.php
- app/Services/Telegram/Handlers/InviteToInterviewHandler.php
- app/Services/Telegram/UrlGenerators/ApplicationUrlGenerator.php
- 5 нових test-файлів

ОНОВЛЕНІ ФАЙЛИ:
- app/Listeners/[NewApplicationListener].php — використовує sendMessageWithKeyboard
- app/Providers/AppServiceProvider.php — реєстрація handler'ів (tagged services)
- lang/uk/notifications.php (або відповідник) — нові тексти

ТЕСТИ:
- NewApplicationNotificationWithKeyboardTest: 5/5
- RejectApplicationHandlerTest: 5/5
- InviteToInterviewHandlerTest: 5/5
- ApplicationUrlGeneratorTest: 3/3
- ApplicationRejectionFlowTest: 3/3
- CandidateStatusChangedNotificationTest: 2/2
- ВСЬОГО НОВИХ: 23/23 PASS
- FULL SUITE: 439/439 PASS
- REGRESSIONS: 0
- BUG #1 (Bearer) covered: ✓
- BUG #2 (chat_id int) covered: ✓

CALLBACK ACTIONS ЗАРЕЄСТРОВАНІ:
- reject:application:{id}
- invite:application:{id}

URL BUTTONS:
- 📄 Переглянути CV → route('employer.applications.cv', ...) АБО fallback
- 💬 Написати → route('employer.chat.with', ...)

UAT TODO (для тебе після merge):
- Створити тестову вакансію на UAT
- Залогінитись як тестовий кандидат, відгукнутись
- У Telegram роботодавця має прийти повідомлення з 4 кнопками
- Натиснути ✅ Запросити → перевірити що:
  (a) Кнопки прибрані, додано "✅ Запрошено о ..."
  (b) Кандидат отримав сповіщення про запрошення
  (c) Статус заявки у БД = InterviewInvited
- Аналогічний UAT для ❌ Відхилити
```

---

## 🚫 Що НЕ робити в цьому промпті

- НЕ створювати handler'ів для view_cv та write (це URL buttons, не callbacks)
- НЕ створювати async interview через `InviteToInterviewHandler` (preset questions не існує)
- НЕ міняти Python-бот код (вся логіка на Laravel-стороні)
- НЕ створювати UI для admin'а редагувати template-тексти (hardcoded/lang-файл)
- НЕ робити migrations — нові поля БД не потрібні (статуси існують)
- НЕ змінювати `ApplicationStatus` Enum — використати існуючі cases
- НЕ робити commit/push після завершення — чекати інструкцій

---

**Готовність:** Прочитати весь промпт. Розпочати з КРОКУ 1 (Reconnaissance). На кожному STOP-GATE — повний звіт і очікування підтвердження.

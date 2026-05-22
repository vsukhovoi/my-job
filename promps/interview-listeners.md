# Claude Code Prompt: Listeners для асинхронних інтерв'ю

## Контекст

Проект **My Job** — Laravel 13, Livewire 3 (Volt), PHPUnit 12.
Вже реалізовано (10.05.2026):
- Events: `InterviewRequestSent`, `InterviewResponseSubmitted`
- Command: `MarkExpiredInterviews` (щодня)
- Таблиця `notifications` — стандартна Laravel Database Notifications (UUID PK, polymorphic, data JSON, read_at)
- Telegram-бот `@myjob_in_bot` — Python-бот з HTTP endpoint

Виконувати строго в зазначеному порядку. Після кожного кроку — підтвердження.

---

## Крок 1 — Розвідка

```bash
cat app/Events/InterviewRequestSent.php
cat app/Events/InterviewResponseSubmitted.php
find app/Listeners -type f | sort
cat app/Providers/EventServiceProvider.php 2>/dev/null || grep -r "InterviewRequest" app/Providers/
find app -name "*Telegram*" -o -name "*telegram*" | sort
grep -r "telegram\|TelegramService\|sendTelegram" app/Services/ 2>/dev/null | head -10
find app/Notifications -type f | sort
grep -n "telegram\|Telegram\|routeNotification" app/Models/User.php | head -10
cat app/Console/Commands/MarkExpiredInterviews.php
```

Покажи результат. **Зупинись і чекай підтвердження.**

---

## Крок 2 — `InterviewRequestNotification` (для кандидата)

**Підтвердь перед виконанням.**

Файл: `app/Notifications/InterviewRequestNotification.php`

Надсилається кандидату коли роботодавець створив запит на асинхронне інтерв'ю.
Канали: `database` + `telegram` (якщо у User є telegram_chat_id; якщо відсутній — тільки database, без помилки).

database `data()`:
```php
[
    'type'       => 'interview_request',
    'request_id' => $interviewRequest->id,
    'vacancy'    => $interviewRequest->vacancy->title,
    'company'    => $interviewRequest->vacancy->company->name,
    'expires_at' => $interviewRequest->expires_at->toDateTimeString(),
    'url'        => route('...'), // знайти реальний роут перегляду запиту кандидатом
]
```

Telegram текст (українською):
```
💼 Новий запит на інтерв'ю
Компанія: {company}
Вакансія: {vacancy}
Відповісти до: {expires_at}
👉 {url}
```

Знайти як у проекті реалізовано надсилання через Telegram — використати існуючий підхід, не вигадувати новий.

**Зупинись і чекай підтвердження.**

---

## Крок 3 — `InterviewResponseNotification` (для роботодавця)

**Підтвердь перед виконанням.**

Файл: `app/Notifications/InterviewResponseNotification.php`

Надсилається роботодавцю коли кандидат відповів.
Канали: `database` + `telegram`.

database `data()`:
```php
[
    'type'       => 'interview_response',
    'request_id' => $interviewRequest->id,
    'candidate'  => $interviewRequest->candidate->name,
    'vacancy'    => $interviewRequest->vacancy->title,
    'url'        => route('employer.interview-requests.show', $interviewRequest),
]
```

Telegram текст (українською):
```
✅ Кандидат відповів на запит інтерв'ю
Кандидат: {candidate}
Вакансія: {vacancy}
👉 {url}
```

**Зупинись і чекай підтвердження.**

---

## Крок 4 — `InterviewReminderNotification` (нагадування кандидату)

**Підтвердь перед виконанням.**

Файл: `app/Notifications/InterviewReminderNotification.php`

Надсилається кандидату за 24г до дедлайну якщо статус ще `pending`.
Канали: `database` + `telegram`.

database `data()`:
```php
[
    'type'       => 'interview_reminder',
    'request_id' => $interviewRequest->id,
    'vacancy'    => $interviewRequest->vacancy->title,
    'expires_at' => $interviewRequest->expires_at->toDateTimeString(),
    'url'        => route('...'), // той самий роут що в InterviewRequestNotification
]
```

Telegram текст (українською):
```
⏰ Нагадування: запит на інтерв'ю
Вакансія: {vacancy}
Термін відповіді спливає: {expires_at}
👉 {url}
```

**Зупинись і чекай підтвердження.**

---

## Крок 5 — Listener `SendInterviewRequestNotification`

**Підтвердь перед виконанням.**

Файл: `app/Listeners/SendInterviewRequestNotification.php`

```php
// Слухає: InterviewRequestSent
// implements ShouldQueue
// handle(): $event->interviewRequest->candidate->notify(new InterviewRequestNotification($event->interviewRequest))
```

Зареєструвати в `EventServiceProvider` (або де реєструються events у проекті):
```php
InterviewRequestSent::class => [
    SendInterviewRequestNotification::class,
],
```

**Зупинись і чекай підтвердження.**

---

## Крок 6 — Listener `SendInterviewResponseNotification`

**Підтвердь перед виконанням.**

Файл: `app/Listeners/SendInterviewResponseNotification.php`

```php
// Слухає: InterviewResponseSubmitted
// implements ShouldQueue
// handle(): $event->interviewRequest->employer->notify(new InterviewResponseNotification($event->interviewRequest))
```

Зареєструвати поруч з попереднім.

**Зупинись і чекай підтвердження.**

---

## Крок 7 — Міграція + розширення `MarkExpiredInterviews`

**Підтвердь перед виконанням.**

Створити міграцію:
```bash
php artisan make:migration add_reminder_sent_at_to_interview_requests_table
```

```php
$table->timestamp('reminder_sent_at')->nullable()->after('expires_at');
```

> `php artisan migrate` — тільки після підтвердження.

Відкрити існуючу команду `MarkExpiredInterviews`. **Не переписувати — додати** логіку нагадування поряд з існуючою в `handle()`:

```php
// Знайти запити де:
// - status = InterviewRequestStatus::Pending
// - expires_at між now() і now()->addHours(24)
// - reminder_sent_at IS NULL

// Для кожного:
$request->candidate->notify(new InterviewReminderNotification($request));
$request->update(['reminder_sent_at' => now()]);
```

**Зупинись і чекай підтвердження.**

---

## Крок 8 — PHPUnit тести

**Підтвердь перед виконанням.**

Файл: `tests/Feature/Employer/InterviewListenersTest.php`

Синтаксис: `#[Test]`, `RefreshDatabase`, `Notification::fake()`.

```
1. interview_request_sends_database_notification_to_candidate()
   Notification::fake()
   dispatch(new InterviewRequestSent($interviewRequest))
   Notification::assertSentTo($candidate, InterviewRequestNotification::class)

2. interview_response_sends_database_notification_to_employer()
   dispatch(new InterviewResponseSubmitted($interviewRequest))
   Notification::assertSentTo($employer, InterviewResponseNotification::class)

3. reminder_sent_to_candidate_24h_before_deadline()
   expires_at = now()->addHours(23), status=Pending, reminder_sent_at=null
   artisan('mark-expired-interviews')
   Notification::assertSentTo($candidate, InterviewReminderNotification::class)

4. reminder_not_sent_twice()
   reminder_sent_at вже заповнено
   Notification::assertNothingSent()

5. reminder_not_sent_if_already_answered()
   status = InterviewRequestStatus::Answered
   Notification::assertNothingSent()

6. existing_expire_logic_still_works()
   expires_at = now()->subHour(), status=Pending
   artisan('mark-expired-interviews')
   assertDatabaseHas з status=Expired
```

**Зупинись і чекай підтвердження.**

---

## Чеклист

**Notification-класи:**
- [ ] `InterviewRequestNotification` — database + telegram (graceful fallback без chat_id)
- [ ] `InterviewResponseNotification` — database + telegram
- [ ] `InterviewReminderNotification` — database + telegram

**Listeners:**
- [ ] `SendInterviewRequestNotification` — ShouldQueue, зареєстровано в EventServiceProvider
- [ ] `SendInterviewResponseNotification` — ShouldQueue, зареєстровано

**Нагадування:**
- [ ] Міграція `reminder_sent_at` виконана
- [ ] `MarkExpiredInterviews` розширено без переписування існуючої логіки

**Якість:**
- [ ] 6/6 тестів зелені
- [ ] `php artisan test --stop-on-failure` — нуль регресій

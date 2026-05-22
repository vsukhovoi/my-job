# Claude Code Prompt — Sanity Check: Interview Placeholder Routes

**Проєкт:** My Job (myjob.co.ua)
**Тип:** Sanity check + (можливо) маленький fix
**Мета:** Переконатись, що маршрути `interview.respond` і `interview.view`, додані під час Фази 1, ведуть на реальні Volt-компоненти модуля Async Interviews (10.05.2026), а не на заглушки.

---

## Контекст

Під час Фази 1 у `routes/web.php` додано **2 deep-link маршрути** для Telegram-сповіщень:
- `interview.respond` — посилання з повідомлення кандидату («📝 Відповісти на питання»)
- `interview.view` — посилання з повідомлення роботодавцю («👀 Переглянути відповіді»)

Модуль Async Interviews був завершений 10.05.2026 з трьома Volt-компонентами:
- `request-form` — форма створення запиту (роботодавець)
- `response-form` — форма відповіді на запит (кандидат)
- `response-view` — перегляд відповіді (роботодавець)

**Питання:** чи placeholder-маршрути правильно підключені до `response-form` і `response-view`, чи це заглушки, які повертають 404 / стат. view?

---

## 🛑 КРИТИЧНІ ПРАВИЛА

1. Починати з **READ-ONLY reconnaissance** — нічого не змінювати на цьому етапі
2. Якщо маршрути коректно підключені → **тільки звіт**, жодних змін
3. Якщо маршрути — заглушки → **виправити підключення** до існуючих Volt-компонентів
4. Volt-only компоненти (НЕ standard Livewire)
5. `actingAs()` до `Volt::test()` (де застосовується в тестах)
6. Enum для ролей/статусів
7. **Не створювати** нові Volt-компоненти — використати ті, що вже є з модуля Async Interviews

---

## 🔍 КРОК 1 — Reconnaissance (READ-ONLY)

### 1.1. Прочитати поточний стан маршрутів
- `routes/web.php` — знайти секцію з маршрутами `interview.respond` і `interview.view`
- Зафіксувати:
  - Який метод HTTP (GET ймовірно)
  - Який URI pattern (`/interview/respond/{id}`? `/interviews/{request}/respond`?)
  - На що вказує route (Closure? Controller? Volt::route?)
  - Middleware (auth, role, etc.)

### 1.2. Прочитати існуючі Volt-компоненти Async Interviews
Знайти та прочитати **усі** три компоненти:
- `resources/views/livewire/interview/request-form.blade.php` (або схожий шлях)
- `resources/views/livewire/interview/response-form.blade.php`
- `resources/views/livewire/interview/response-view.blade.php`

Або в іншій структурі — пошукати через:
```bash
find resources/views/livewire -name "*interview*"
find resources/views -name "*response-form*" -o -name "*response-view*"
```

### 1.3. Прочитати listener'и Фази 1
- `app/Listeners/NotifyInterviewRequestSent.php` — як саме формується URL для кандидата (`route('interview.respond', ...)`)
- `app/Listeners/NotifyInterviewResponseSubmitted.php` — як формується URL для роботодавця (`route('interview.view', ...)`)
- Звернути увагу: які параметри передаються в route helper (`$request->id`? `$response->id`?)

### 1.4. Перевірити модель/relationships
- `app/Models/InterviewRequest.php` — методи, relationships (`candidate`, `vacancy`, `responses`)
- `app/Models/InterviewResponse.php` — relationships (`interviewRequest`, `candidate`)
- Зрозуміти: чи route очікує `InterviewRequest` ID чи `InterviewResponse` ID?

### ⏸ STOP-GATE 1 — Класифікація стану

Вивести **діагноз** у одному з 4 форматів:

**Сценарій А — Все правильно з самого початку**
```
✅ ROUTES CORRECTLY WIRED

routes/web.php:
- GET /interview/respond/{interviewRequest} → Volt::route('interview.respond-form')
  Middleware: ['auth', 'role:candidate']
- GET /interview/view/{interviewResponse} → Volt::route('interview.response-view')
  Middleware: ['auth', 'role:employer']

Listeners:
- NotifyInterviewRequestSent → route('interview.respond', $request->id) ✓
- NotifyInterviewResponseSubmitted → route('interview.view', $response->id) ✓

Volt components:
- response-form: ✓ existed, accepts InterviewRequest mount param
- response-view: ✓ existed, accepts InterviewResponse mount param

ВИСНОВОК: Жодних змін не потрібно. Sanity check passed.
```

**Сценарій Б — Stub routes (заглушки)**
```
⚠️ ROUTES ARE STUBS

routes/web.php:
- Route::get('/interview/respond/{id}', fn() => 'placeholder')->name('interview.respond');
- Route::get('/interview/view/{id}', fn() => 'placeholder')->name('interview.view');

Існуючі Volt-компоненти:
- response-form: ✓ існує за шляхом X
- response-view: ✓ існує за шляхом Y

ВИСНОВОК: Потрібно переписати маршрути на Volt::route() з правильним підключенням. Перейти до КРОКУ 2.
```

**Сценарій В — Часткове підключення**
```
⚠️ PARTIAL WIRING

interview.respond → правильно підключено до response-form
interview.view → закладено як заглушку

ВИСНОВОК: Виправити тільки interview.view. Перейти до КРОКУ 2 (звужений).
```

**Сценарій Г — Volt-компоненти не знайдені**
```
❌ MISSING VOLT COMPONENTS

Очікувані компоненти Async Interviews модуля (10.05.2026) НЕ знайдено:
- response-form: НЕ знайдено в resources/views/livewire/
- response-view: НЕ знайдено

Це critical issue — модуль або не закомічений, або компоненти в іншому місці.

ВИСНОВОК: ЗУПИНИТИСЬ. Чекати рішення користувача.
```

**Зупинитись.** Чекати "go" перед переходом до КРОКУ 2 (або підтвердження що Sanity Check завершено успішно).

---

## 🛠 КРОК 2 — Fix (тільки якщо Сценарій Б або В)

**Виконується ТІЛЬКИ якщо reconnaissance показав, що маршрути — заглушки.**

### 2.1. Замінити заглушки на Volt::route()

**Для `interview.respond` (форма відповіді кандидата):**
```php
Volt::route('/interview/respond/{interviewRequest}', 'interview.response-form')
    ->middleware(['auth'])
    ->name('interview.respond');
```
(точна назва Volt-route — з reconnaissance)

**Для `interview.view` (перегляд відповіді роботодавцем):**
```php
Volt::route('/interview/view/{interviewResponse}', 'interview.response-view')
    ->middleware(['auth'])
    ->name('interview.view');
```

**Важливо:**
- Використати **точно ті назви Volt-route'ів**, які виявилися в reconnaissance
- НЕ створювати нові компоненти — тільки підключити існуючі
- Middleware — за патерном, що вже існує в інших route'ах модуля

### 2.2. Авторизація / Policy
Перевірити, чи Volt-компоненти `response-form` і `response-view` мають внутрішню перевірку прав:
- `response-form` — тільки кандидат, якому адресовано запит
- `response-view` — тільки роботодавець, який цей запит створив (або має доступ до вакансії)

Якщо перевірка вже всередині Volt-компонента (через `mount()` + `abort_if`) → middleware просто `auth`
Якщо немає → потрібно додати `can:` middleware або перевірку у Volt (тільки якщо це **виправлення явного багу**, не нова функціональність)

### 2.3. Параметри в listener'ах
Перевірити узгодженість з reconnaissance:

```php
// NotifyInterviewRequestSent
$url = route('interview.respond', $event->interviewRequest);
// або
$url = route('interview.respond', $event->interviewRequest->id);
```

**Узгодити з implicit model binding** — якщо route bind `{interviewRequest}`, то passing model працює напряму через implicit binding. Якщо route bind `{id}`, треба передавати ID.

### 2.4. Перевірка через тестування
Запустити **існуючі** тести Phase 1:
```bash
php artisan test --filter=InterviewNotificationsTest
```
Очікуваний результат: **6/6 PASS** (нічого не зламано).

Запустити **повний** test suite:
```bash
php artisan test
```
Очікуваний результат: **409/409 PASS, zero regressions**.

### ⏸ STOP-GATE 2

Вивести звіт:
```
ROUTES FIXED:
- interview.respond → Volt::route(/interview/respond/{interviewRequest}, 'X')
- interview.view → Volt::route(/interview/view/{interviewResponse}, 'Y')

LISTENERS UPDATED: [yes/no, з поясненням]

TESTS:
- InterviewNotificationsTest: 6/6 PASS
- Full suite: [N]/[N] PASS
- Regressions: 0
```

---

## 🧪 КРОК 3 — E2E smoke test (опційно, тільки якщо КРОК 2 виконувався)

Створити **один** smoke-тест, який підтверджує E2E flow:

**Файл:** `tests/Feature/Interview/InterviewDeepLinkRoutesTest.php`

3 тести:

1. `it_renders_response_form_for_authorized_candidate`
2. `it_renders_response_view_for_authorized_employer`
3. `it_returns_403_for_unauthorized_user_accessing_someone_elses_interview`

**Конвенції:**
- `#[Test]` атрибут
- `actingAs()` ДО `Volt::test()` (якщо використовується)
- Enum-based ролі: `UserRole::Candidate`, `UserRole::Employer`
- Factory-based створення InterviewRequest/InterviewResponse

Запустити:
```bash
php artisan test --filter=InterviewDeepLinkRoutesTest
```
Очікуваний результат: **3/3 PASS**.

### ⏸ STOP-GATE 3
Вивести фінальний звіт по smoke-тестах.

---

## 📋 ФІНАЛЬНИЙ ЗВІТ

```
═══════════════════════════════════════
SANITY CHECK — Interview Deep-Link Routes
═══════════════════════════════════════

ДІАГНОЗ ДО ЗМІН: [Сценарій А / Б / В / Г]

ЗМІНИ:
- routes/web.php: [перелік]
- listeners: [перелік або "не потрібно"]
- нові тести: [3 / 0]

ФІНАЛЬНИЙ TEST SUITE: [N]/[N] PASS
REGRESSIONS: 0

PHASE 1 ГОТОВІСТЬ ДО PRODUCTION: ✅
```

---

## 🚫 Що НЕ робити в цьому промпті

- НЕ створювати нові Volt-компоненти (тільки використати існуючі)
- НЕ міняти логіку в Volt-компонентах модуля Async Interviews
- НЕ створювати нові routes окрім тих 2, що вже існують
- НЕ міняти listener'и без явної необхідності (тільки якщо параметри в route() не узгоджені)
- НЕ робити commit / push

---

**Готовність:** Прочитати весь промпт. Розпочати з КРОКУ 1 (Reconnaissance). На STOP-GATE 1 — дочекатись підтвердження сценарію.

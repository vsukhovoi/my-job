# Глосарій проекту My Job

## Ролі користувачів (`App\Enums\UserRole`)

| Enum | Value | Назва | Опис |
|------|-------|-------|------|
| `UserRole::Candidate` | `candidate` | Кандидат | Шукач роботи — може подавати заявки, створювати резюме |
| `UserRole::Employer` | `employer` | Роботодавець | Представник компанії — публікує вакансії, розглядає заявки |
| `UserRole::Admin` | `admin` | Адміністратор | Повний доступ до Filament-панелі (`/admin`) |

---

## Статуси вакансій (`App\Enums\VacancyStatus`)

| Enum | Value | Назва | Поведінка |
|------|-------|-------|-----------|
| `VacancyStatus::Draft` | `draft` | Чернетка | Не опубліковано — бачить лише автор |
| `VacancyStatus::Active` | `active` | Активна | Публікується на сайті та в пошуку |
| `VacancyStatus::Expired` | `expired` | Завершена | Час вийшов; SEO-сторінка доступна за прямим URL |
| `VacancyStatus::Archived` | `archived` | Архів | Знята з пошуку, повертає 404 за прямим URL |

---

## Типи зайнятості (`App\Enums\EmploymentType`)

| Enum | Value | Назва |
|------|-------|-------|
| `EmploymentType::FullTime` | `full-time` | Повна зайнятість |
| `EmploymentType::PartTime` | `part-time` | Часткова зайнятість |
| `EmploymentType::Remote` | `remote` | Віддалено |
| `EmploymentType::Hybrid` | `hybrid` | Гібрид |
| `EmploymentType::Contract` | `contract` | Контракт |

---

## Статуси заявок (`App\Enums\ApplicationStatus`)

| Enum | Value | Назва | Хто може встановити |
|------|-------|-------|---------------------|
| `ApplicationStatus::Pending` | `pending` | Новий | — (початковий) |
| `ApplicationStatus::Screening` | `screening` | Розгляд | employer |
| `ApplicationStatus::Interview` | `interview` | Співбесіда | employer |
| `ApplicationStatus::Hired` | `hired` | Прийнятий | employer |
| `ApplicationStatus::Rejected` | `rejected` | Відхилений | employer / seeker |
| `ApplicationStatus::Withdrawn` | `withdrawn` | Відкликано | seeker |

---

## Статуси співбесід (`App\Enums\InterviewStatus`)

| Enum | Value | Назва |
|------|-------|-------|
| `InterviewStatus::Scheduled` | `scheduled` | Заплановано |
| `InterviewStatus::Confirmed` | `confirmed` | Підтверджено |
| `InterviewStatus::Rescheduled` | `rescheduled` | Перенесено |
| `InterviewStatus::Cancelled` | `cancelled` | Скасовано |

---

## Типи співбесід (`App\Enums\InterviewType`)

| Enum | Value | Назва | Потребує |
|------|-------|-------|----------|
| `InterviewType::Video` | `video` | Відеозустріч | посилання на відеодзвінок |
| `InterviewType::Phone` | `phone` | Телефонна розмова | — |
| `InterviewType::InPerson` | `in_person` | Очна зустріч (офіс) | адресу |
| `InterviewType::Other` | `other` | Інший формат | — |

---

## Типи повідомлень (`App\Enums\MessageType`)

| Enum | Value | Назва |
|------|-------|-------|
| `MessageType::Invitation` | `invitation` | Запрошення на співбесіду |
| `MessageType::Message` | `message` | Стандартне повідомлення |
| `MessageType::Offer` | `offer` | Пропозиція про роботу |
| `MessageType::Rejection` | `rejection` | Відхилення |

---

## Категорії придатності (`App\Enums\Suitability`)

| Enum | Value | Назва |
|------|-------|-------|
| `Suitability::NoExperience` | `no_experience` | Кандидатам без досвіду |
| `Suitability::NoResume` | `no_resume` | Кандидатам без резюме |
| `Suitability::Students` | `students` | Студентам |
| `Suitability::Disabilities` | `disabilities` | Людям з інвалідністю |
| `Suitability::Pensioners` | `pensioners` | Пенсіонерам |

---

## Мови (`App\Enums\Language`)

| Enum | Value | Назва |
|------|-------|-------|
| `Language::Ukrainian` | `uk` | Українська |
| `Language::English` | `en` | Англійська |
| `Language::German` | `de` | Німецька |
| `Language::Spanish` | `es` | Іспанська |
| `Language::Polish` | `pl` | Польська |

---

## Типи публікації вакансії (`App\Enums\VacancyPublicationType`)

| Enum | Value | Назва |
|------|-------|-------|
| `VacancyPublicationType::Standard`  | `standard`  | Звичайна |
| `VacancyPublicationType::Anonymous` | `anonymous` | Анонімна |

---

## Ключові терміни архітектури

### Entities (Сутності)

| Термін | Клас | Таблиця | Опис |
|--------|------|---------|------|
| **Vacancy** | `App\Models\Vacancy` | `vacancies` | Оголошення про вакансію від роботодавця |
| **Application** | `App\Models\Application` | `applications` | Заявка кандидата на вакансію |
| **Company** | `App\Models\Company` | `companies` | Компанія-роботодавець |
| **Category** | `App\Models\Category` | `categories` | Категорія вакансій (slug-indexed) |
| **City** | `App\Models\City` | `cities` | Місто з geo-координатами (lat/lng) |
| **Resume** | `App\Models\Resume` | `resumes` | Резюме кандидата |
| **Interview** | `App\Models\Interview` | `interviews` | Запланована співбесіда |
| **ApplicationNote** | `App\Models\ApplicationNote` | `application_notes` | Нотатка роботодавця до заявки |
| **CandidateMessage** | `App\Models\CandidateMessage` | `candidate_messages` | Повідомлення роботодавця кандидату |
| **MessageTemplate** | `App\Models\MessageTemplate` | `message_templates` | Шаблон повідомлення |
| **TelegramSubscription** | `App\Models\TelegramSubscription` | `telegram_subscriptions` | Підписка на Telegram-сповіщення |

### Services (Сервіси)

| Сервіс | Відповідальність |
|--------|-----------------|
| `VacancyService` | CRUD вакансій, featured-логіка, публікація |
| `ApplicationService` | Подача заявки, зміна статусів |
| `TelegramService` | Відправка повідомлень через Telegram Bot API |
| `TelegramAuthService` | Авторизація через Telegram deep-link |
| `CommunicationService` | Повідомлення кандидатам на основі шаблонів |
| `InterviewService` | Управління співбесідами, нагадування |
| `PaymentService` | Stripe-інтеграція (featured, top вакансії) |
| `SeoService` | Meta-теги, sitemap, canonical URL |
| `PhoneOtpService` | OTP-верифікація номера телефону |

### Feature-терміни вакансій

| Термін | Поле БД | Опис |
|--------|---------|------|
| **Featured** | `is_featured`, `featured_until` | Платне виділення вакансії у списку |
| **Top** | `is_top` | Вакансія закріплена на початку списку |
| **Active** | `is_active` | Вакансія відображається у пошуку |
| **Salary range** | `salary_from`, `salary_to`, `currency` | Вилка зарплати |
| **Suitability** | `suitability` (JSON) | Категорії кандидатів, для яких підходить вакансія |
| **Languages** | `languages` (JSON) | Мови, необхідні для позиції |

---

## Інфраструктура

| Термін | Що означає |
|--------|-----------|
| **Livewire Volt** | Компонентний фреймворк для реактивного UI (Class API). Файли у `resources/views/livewire/` |
| **Filament** | Адмін-панель (`/admin`). Resources у `app/Filament/Resources/` |
| **Nutgram** | PHP-пакет для Telegram Bot API (`nutgram/nutgram`) |
| **Redis** | Кешування + черги (Laravel Queues) |
| **Resend** | SMTP-провайдер для email (`smtp.resend.com`) |
| **Stripe** | Платіжна система для featured/top вакансій |
| **Cloudflare** | DNS + SSL-проксі (режим Full, SSL термінується на VPS) |
| **Docker** | Оточення на VPS (OVHcloud). Команди через `docker compose exec app` |

---

## Маршрути (Key Routes)

| URL | Опис |
|-----|------|
| `/` | Головна — список вакансій (Livewire) |
| `/jobs/{slug}` | Сторінка вакансії + форма заявки |
| `/dashboard/employer` | Панель роботодавця |
| `/admin` | Filament-адмін (роль: admin) |
| `POST /telegram/webhook` | Вебхук Telegram-бота |
| `POST /stripe/webhook` | Вебхук Stripe |
| `/sitemap.xml` | XML-сайтмап (кеш 24 год) |

---

## CSS / Теми

| Термін | Опис |
|--------|------|
| **Light theme** | Базові CSS-класи без префікса в `app.css` |
| **Dark theme** | Overrides з префіксом `html[data-theme="dark"]` в `app.css` |
| **mj-*** | Власні CSS-класи проекту (mj = My Job). Приклад: `.mj-card`, `.mj-btn` |
| **Brand orange** | `#F36F21` — основний акцентний колір бренду |

---

---

## Модальні вікна

| # | Назва | Тригер | Файл |
|---|-------|--------|------|
| **#1** | Email Setup Modal | автоматично для роботодавців без email | `livewire/employer/email-setup-modal.blade.php` |
| **#2** | Quick Publish Modal | `Livewire.dispatch('open-quick-publish')` | `livewire/employer/quick-publish-form.blade.php` |
| **#3** | Limit Modal (Livewire) | `$wire.showLimit = true` при перевищенні ліміту | `livewire/employer/quick-publish-form.blade.php` |
| **#4** | Limit Modal (session) | `session('limit_exceeded')` | `components/employer-tabs.blade.php` |
| **#5** | Mobile Filters Modal | кнопка «☰» на сторінці вакансій | `livewire/pages/jobs/index.blade.php` |
| **#6** | Telegram Auth Modal (логін) | «Увійти через Telegram» | `livewire/pages/auth/login.blade.php` |
| **#7** | Telegram Auth Modal (резюме) | «Увійти через Telegram» у майстрі резюме | `livewire/resume-steps/auth-step.blade.php` |
| **#8** | Delete Account Modal | кнопка «Delete Account» | `livewire/profile/delete-user-form.blade.php` |
| **#9** | Vacancy Published Modal | `session('vacancy_published_id')` | `layouts/app.blade.php` |
| **#10** | Profile Required Modal | `toggleActive()` при неповному профілі | `livewire/pages/employer/dashboard.blade.php` |
| **#11** | wire:confirm | `wire:confirm="..."` на кнопках дій | різні файли |

---

### 1. Email Setup Modal — налаштування пошти роботодавця

**Файл:** `resources/views/livewire/employer/email-setup-modal.blade.php`  
**Підключення:** `layouts/app.blade.php` (глобально для всіх сторінок)  
**Тригер:** показується автоматично роботодавцям без email (`$wire.show`)  
**Розмір:** `max-w-sm`, `rounded-2xl`, `p-8`  
**Backdrop:** `bg-black/60`

Два кроки (`$step`):

| Крок | Дія | Іконка |
|------|-----|--------|
| `email` | Введення email → `wire:submit="sendCode"` | синій envelope (`bg-blue-100`) |
| `code` | 6-значний OTP-код → `wire:submit="verify"` | зелений checkmark (`bg-green-100`) |

Закриття: `@keydown.escape` → `$wire.skip()`

---

### 2. Quick Publish Modal — швидка публікація вакансії

**Файл:** `resources/views/livewire/employer/quick-publish-form.blade.php`  
**Тригер:** `Livewire.dispatch('open-quick-publish')` — кнопка «Нова вакансія» у хедері (`employer-tabs`)  
**Розмір:** `max-w-md`, `rounded-2xl`  
**Backdrop:** `bg-black/60`, клік → закриває (`$wire.show = false`)  
**Анімація:** `opacity-0 scale-95` → `opacity-100 scale-100` (200ms)

Поля форми:

| Поле | Обов'язкове | Тип |
|------|-------------|-----|
| Назва посади | ✅ | text |
| Категорія | ✅ | select |
| Місто | ✅ | `livewire:city-search` |
| Зарплата (від) | ❌ | number |

Дія: `wire:submit="publish"`

---

### 3. Limit Modal — ліміт вакансій вичерпано (Livewire)

**Файл:** `resources/views/livewire/employer/quick-publish-form.blade.php` (перший modal у файлі)  
**Тригер:** `$wire.showLimit = true` — коли `publish()` виявляє перевищення ліміту тарифу  
**Розмір:** `max-w-sm`, `rounded-2xl`, `p-8`, `text-center`  
**Іконка:** помаранчевий замок (`bg-orange-100`)

Кнопки:
- «Оновити тариф» → `route('employer.billing')` (синій)
- «Переглянути всі тарифи» → `route('employer.billing')` (текстовий)
- «Закрити» → `$wire.showLimit = false`

---

### 4. Limit Modal — ліміт вакансій (session flash)

**Файл:** `resources/views/components/employer-tabs.blade.php`  
**Тригер:** `session('limit_exceeded')` = true (flash після редіректу)  
**Розмір:** `max-w-440px`, `border-radius: 20px`, `padding: 36px 32px`  
**Backdrop:** `rgba(0,0,0,0.5)`, клік по backdrop → `closeLimitModal()`  
**JS-функція:** `closeLimitModal()`, закривається також на `Escape`  
**Іконка:** 🔒 emoji (`bg: #fef3c7`, `border-radius: 50%`)

Кнопки:
- «🚀 Оновити тариф» → `route('employer.billing')` (gradient `#2563eb → #4f46e5`)
- «Переглянути всі тарифи» → `route('employer.billing')` (light blue)
- «Закрити» → `closeLimitModal()`

---

### 5. Mobile Filters Modal — фільтри (мобільна версія)

**Файл:** `resources/views/livewire/pages/jobs/index.blade.php` (~рядок 482)  
**Тригер:** кнопка-гамбургер «☰» у хедері списку вакансій → `filtersOpen = true` (Alpine.js)  
**Тип:** bottom sheet (slide-up знизу)  
**Анімація:** `translate-y-full opacity-0` → `translate-y-0 opacity-100` (300ms)  
**Закриття:** клік по backdrop (`@click.self`), кнопка «✕», або «Застосувати фільтри»  
**Вміст:** `@include('livewire.pages.jobs._filters')` — той самий набір фільтрів, що й на десктопі

---

### 6. Telegram Auth Modal — сторінка логіну

**Файл:** `resources/views/livewire/pages/auth/login.blade.php` (~рядок 264)  
**ID:** `#tg-modal`  
**Тригер:** кнопка «Увійти через Telegram» → `telegramLogin(role)` (JS)  
**Розмір:** `max-width: 360px`, `border-radius: 16px`, `padding: 32px 24px`  
**Backdrop:** `rgba(0,0,0,0.5)`  
**Іконка:** Telegram-логотип (`bg: #2AABEE`, круг `56×56px`)

Стани:

| Стан | Заголовок | Вміст |
|------|-----------|-------|
| Очікування | «Відкрийте Telegram» | спінер + посилання «Відкрити бота →» |
| Авторизовано | «✅ Авторизовано!» | «Перенаправляємо...» |
| Прострочено | — | `alert()` + закриття |

Поллінг: `setInterval(3000ms)`, таймаут 5 хв. Закривається на `closeTgModal()`.

---

### 7. Telegram Auth Modal — майстер резюме

**Файл:** `resources/views/livewire/resume-steps/auth-step.blade.php` (~рядок 62)  
**ID:** `#tg-resume-modal`  
**Тригер:** кнопка «Увійти через Telegram» → `startTelegramAuth()` (JS)  
**Розмір:** `max-w-sm`, `rounded-2xl`, `p-6`, `text-center`  
**Backdrop:** `bg-black/60`, клік → `closeTgResumeModal()`

Стани аналогічні до Modal #6. Після авторизації редірект з параметром `?resume_redirect=1`.

---

### 8. Delete Account Modal — підтвердження видалення акаунту

**Файл:** `resources/views/livewire/profile/delete-user-form.blade.php`  
**Компонент:** `<x-modal name="confirm-user-deletion">` (`resources/views/components/modal.blade.php`)  
**Тригер:** `$dispatch('open-modal', 'confirm-user-deletion')` — кнопка «Delete Account»  
**Розмір:** `sm:max-w-2xl` (дефолт компонента)  
**Backdrop:** `bg-gray-500 opacity-75`  
**Закриття:** клік по backdrop, `Escape`, або `$dispatch('close')`  
**Анімація:** `opacity-0 translate-y-4 scale-95` → `opacity-100 translate-y-0 scale-100` (300ms)

Поля: пароль (`current_password`)  
Дія: `wire:submit="deleteUser"` → видалення + logout + redirect `/`

---

### 9. Vacancy Published Modal — вакансія опублікована

**Файл:** `resources/views/layouts/app.blade.php` (~рядок 43)  
**Тригер:** `session('vacancy_published_id')` — flash-сесія після успішної публікації вакансії  
**Тип:** статичний Blade-блок у глобальному layout (не Livewire-компонент)  
**Розмір:** `max-w-md`, `rounded-2xl`, `p-8`, `text-center`  
**Backdrop:** `bg-black/60`  
**Стан:** Alpine.js `{ show: true }` — відкривається автоматично  
**Анімація:** `opacity-0 scale-95` → `opacity-100 scale-100` (200ms)

Вміст:
- Іконка 🚀 (`text-5xl`)
- Заголовок «Вакансія опублікована!»
- Підзаголовок «Ми вже почали шукати кандидатів»
- Amber-блок попередження: «Вакансія активна 1 добу» → заклик заповнити профіль компанії для продовження до 30 діб (`bg-amber-50`, `border-amber-200`)

Кнопки:
- «Заповнити профіль компанії» → `route('employer.profile')` (синій, `bg-blue-600`)
- «Пропустити» → `show = false` (текстовий, `text-gray-400`)

---

### 10. Profile Required Modal — незаповнений профіль компанії

**Змінна:** `showProfileModal` (bool, Livewire)  
**Файл:** `resources/views/livewire/pages/employer/dashboard.blade.php` (~рядок 226)  
**Тригер:** `toggleActive()` — якщо роботодавець намагається активувати вакансію, але профіль компанії не заповнено (`isProfileComplete() === false`)  
**Тип:** Alpine.js `x-data="{ show: $wire.entangle('showProfileModal') }"`  
**Розмір:** `max-w-md`, `rounded-2xl`, `p-8`, `text-center`  
**Backdrop:** `bg-black/60`, клік → `show = false`  
**Анімація:** `opacity-0 scale-95` → `opacity-100 scale-100` (200ms)

Вміст:
- Іконка трикутника-попередження (`bg-amber-100`, `text-amber-500`, 64×64px)
- Заголовок «Вакансія не активована»
- Підзаголовок «Спочатку заповніть профіль компанії»
- Amber-блок: «Вакансія активна 1 добу» — пояснення що без профілю вакансія живе лише добу

Кнопки:
- «Заповнити профіль компанії» → `route('employer.profile')` (синій, `bg-blue-600`)
- «Пропустити» → `show = false` (текстовий, `text-gray-400`)

---

### 11. wire:confirm — нативні діалоги підтвердження

Не є повноцінними модальними вікнами — використовують браузерний `window.confirm()`.  
З'являються **до** виконання Livewire-дії.

| Текст діалогу | Дія | Файл |
|---------------|-----|------|
| «Ви впевнені, що хочете видалити цю вакансію?» | `wire:click="delete($id)"` | `employer/dashboard.blade.php` |
| «Видалити цю нотатку?» | видалення нотатки | `employer/candidate-detail.blade.php` |
| «Скасувати цю співбесіду?» | скасування співбесіди | `employer/candidate-detail.blade.php` |
| «Відхилити кандидата?» | відхилення кандидата | `employer/candidate-detail.blade.php` |
| «Видалити шаблон «…»?» | видалення шаблону | `employer/message-templates.blade.php` |
| «Видалити з збережених?» | видалення зі збережених | `seeker/saved-vacancies.blade.php` |
| «Видалити цей запис?» | видалення досвіду | `resume-steps/experience-step.blade.php` |
| «Перевести резюме в чернетку?» / «Опублікувати резюме?» | toggle резюме | `seeker/resumes.blade.php`, `resumes/show.blade.php` |
| «Видалити резюме «…»? Цю дію не можна відмінити.» | видалення резюме | `seeker/resumes.blade.php`, `resumes/show.blade.php` |

---

### 12. Profile Completeness Modal — заповненість профілю роботодавця

**Файл:** `resources/views/livewire/employer/profile-completeness-modal.blade.php`  
**Компонент:** `<livewire:employer.profile-completeness-modal />`  
**Підключення:** `layouts/app.blade.php` — тільки для `UserRole::Employer`  
**Тип:** centered modal (по центру екрана на всіх breakpoints)  
**Backdrop:** `bg-black/50`, `fixed inset-0`  
**Розмір:** `max-w-md`, `mx-4`, `rounded-2xl`, `p-6`  
**Анімація:** `opacity-0 scale-95` → `opacity-100 scale-100` (300ms enter / 200ms leave)

#### Логіка показу

| Умова | Результат |
|-------|-----------|
| `score < 100` AND `shown_at IS NULL` | показати |
| `score < 100` AND `shown_at < today` | показати (раз на добу) |
| `score < 100` AND `shown_at >= today` | не показувати |
| `score = 100` | не показувати |
| роль не `Employer` | не показувати |

Після показу: `profile_completeness_modal_shown_at = now()` (колонка у `users`).

#### Структура UI (скріншот: dark theme, 50%)

| Елемент | Опис |
|---------|------|
| Заголовок | «Ваш профіль заповнено на {score}% 🎯», `text-lg font-bold` |
| Прогрес-бар | `h-2.5 rounded-full`, колір: green ≥75% / amber ≥40% / red <40% |
| 🚀 Більше довіри | «Профілі зі 100% заповненням отримують на 40% більше відгуків» |
| 🔝 Вище у пошуку | «Повністю готові профілі відображаються першими у списках» |
| ⏱ Економія часу | «Шукачі одразу бачать ваші переваги та умови» |
| CTA кнопка | «Заповнити до 100%» — `bg-blue-600`, `w-full`, `rounded-xl`, `wire:click="goFill"` → `employer.profile` |
| Посилання | «Нагадати пізніше» — сірий текст, `wire:click="dismiss"` → `show = false` |

#### Дії

| Метод | Дія |
|-------|-----|
| `mount()` | обчислює score, перевіряє умову показу, оновлює `shown_at` |
| `dismiss()` | `show = false` |
| `goFill()` | `show = false` + redirect → `route('employer.profile')` |

---

### Базовий компонент `<x-modal>`

**Файл:** `resources/views/components/modal.blade.php`  
**Props:** `name` (string), `show` (bool, default `false`), `maxWidth` (sm/md/lg/xl/2xl, default `2xl`)  
**Відкриття:** `$dispatch('open-modal', 'name')` (глобальна подія)  
**Закриття:** `$dispatch('close-modal', 'name')` або `$dispatch('close')`  
**Keyboard:** Tab trap (фокус у межах modal), `Escape` → закриває

---

## Елементи дизайну

### Перемикач активності вакансії (Toggle)

**Файл:** `resources/views/livewire/pages/employer/dashboard.blade.php` (рядок ~161)  
**Місце:** остання колонка таблиці вакансій, після колонки «ОПУБЛІКОВАНО»  
**Дія:** `wire:click="toggleActive($vacancy->id)"` → перемикає статус між `Active` ↔ `Draft`

#### Розміри та форма

| Параметр | Значення |
|---------|---------|
| Ширина | `44px` |
| Висота | `24px` |
| Border-radius | `999px` (pill) |
| Кнопка (knob) | `20px × 20px`, `border-radius: 50%`, `background: #FFFFFF` |
| Shadow knob | `0 1px 4px rgba(0,0,0,.2)` |

#### Стани

| Стан | Фон track | Позиція knob | Title tooltip |
|------|-----------|-------------|--------------|
| **Активна** (`Active`) | `#16a34a` (green-600) | `left: 22px` | «Деактивувати» |
| **Неактивна** (`Draft`) | `#D1D5DB` (gray-300) | `left: 2px` | «Активувати» |

#### Анімація

| Властивість | Тривалість |
|------------|-----------|
| `background` | `0.25s` |
| `left` (knob) | `0.25s` |

#### Логіка (метод `toggleActive`)

```
Active  → forceFill(status: Draft, is_active: false)
Draft / Expired / Archived → $vacancy->publish()
```

---

### CTA-кнопки продовження вакансії

**Файл:** `resources/views/livewire/pages/jobs/show.blade.php` (рядки 1481–1482, інлайн `<style>`)  
**Умова відображення:** змінна `$sidebarState` (рядок 30)

| Стан `$sidebarState` | Текст кнопки | CSS-клас | Фон | Текст |
|----------------------|-------------|----------|-----|-------|
| `'expiring'` | «Продовжити вакансію» | `.mj-employer-cta--warning` | `#d97706` (amber-600) | `#fff` |
| `'expired'` | «Відновити вакансію» | `.mj-employer-cta--danger` | `#dc2626` (red-600) | `#fff` |

Базовий клас `.mj-employer-cta`: `display:block`, `padding: 10px 14px`, `border-radius: 8px`, `font-size: 14px`, `font-weight: 600`, `transition: opacity 0.2s`.  
Hover: `opacity: 0.85`.  
Підказка під кнопкою (`.mj-employer-cta-hint`): «15 / 30 / 90 днів», `font-size: 11px`, `color: #9ca3af`.  
Маршрут: `route('employer.vacancies.extend', $vacancy)`.

---

### Модуль заповненості профілю (Profile Completeness)

**Компонент:** `<livewire:shared.profile-completeness type="employer|vacancy|candidate" />`  
**Файл:** `resources/views/livewire/shared/profile-completeness.blade.php`  
**Сервіс:** `App\Services\ProfileCompletenessService`  
**Місце в хедері:** `employer-tabs.blade.php` — Col 2 з 3, `<div class="flex justify-center">`  
**Умова рендеру:** тільки якщо `$company` існує (`@if($company)`)  
**Реактивність:** `x-on:profile-saved.window="$wire.$refresh()"` — автоматично перераховує після збереження профілю

#### Типи (`$type`)

| Тип | Метод сервісу | Що рахує |
|-----|--------------|---------|
| `employer` | `employerScore(User)` | Поля компанії + user |
| `vacancy` | `vacancyScore(Vacancy)` | Поля вакансії |
| `candidate` | `candidateScore(User)` | Поля резюме |

#### Поля роботодавця (`employerScore`) з вагами

| Поле | Вага | Джерело |
|------|------|---------|
| Назва компанії | 15% | `company->name` |
| Опис компанії | 20% | `company->description` |
| Веб-сайт | 15% | `company->website` |
| Місто | 15% | `company->city_id` або `location` |
| Логотип | 10% | `company->logo` |
| ЄДРПОУ / ІПН | 10% | `company->tax_id` |
| Telegram ID | 10% | `user->telegram_id` |
| Телефон | 5% | `user->phone` |
| **Разом** | **100%** | |

#### Кольори прогрес-бару

| Діапазон | Колір | Значення |
|---------|-------|---------|
| `≥ 75%` | Зелений | `#16a34a` |
| `40–74%` | Amber | `#d97706` |
| `< 40%` | Червоний | `#dc2626` |

#### Структура UI (2 рядки)

**Рядок 1:** підпис «Заповненість профілю» + прогрес-бар (`h-1.5`, `rounded-full`, `transition 500ms`) + `score%`  
**Рядок 2 (якщо `score < 100`):** «Наступний крок: {label}» + кнопка «Заповнити» (`bg #F36F21`, `px-3 py-1 rounded-lg`)  
**Рядок 2 (якщо `score = 100`):** «Профіль заповнений ✓» (`text-green-600`)

Наступний крок — поле з **найбільшою вагою** серед незаповнених (`sortByDesc('weight')`).

#### Розміри контейнера

`w-full`, `min-w-0`, `rounded-xl`, `border`, `px-4 py-3`, `space-y-2`  
Теми: `bg-white dark:bg-gray-800`, `border-gray-200 dark:border-gray-700`

---

### Кнопка «+ Нова вакансія»

**Файл:** `resources/views/components/employer-tabs.blade.php` (~рядок 122)  
**Місце:** правий край хедера панелі роботодавця (`employer-tabs`), Col 3 з 3  
**Тригер:** `onclick="Livewire.dispatch('open-quick-publish')"` → відкриває Quick Publish Modal (#2)

#### Стилі (inline)

| Параметр | Значення |
|---------|---------|
| Фон | `#2563eb` (blue-600) |
| Колір тексту | `#fff` |
| Padding | `9px 18px` |
| Font-size | `0.875rem` (14px) |
| Font-weight | `700` |
| Border-radius | `12px` |
| Shadow | `0 1px 4px rgba(37,99,235,.3)` |
| Gap (іконка + текст) | `6px` |
| **Висота (розрахована)** | `38px` (9 + line-height 20 + 9) |
| **Ширина (розрахована)** | `≈142px` (18 + icon 16 + gap 6 + текст ≈84 + 18) |
| **Висота mobile (`< sm`)** | `50px` (фіксована, `.btn-new-vacancy` медіа-запит) |
| **Ширина mobile (`< sm`)** | `50px` (фіксована, `.btn-new-vacancy` медіа-запит) |

#### Адаптивність

| Breakpoint | Контент | Реалізація |
|-----------|---------|-----------|
| `< 640px` (mobile) | тільки SVG «+» | `<span class="hidden sm:inline">Нова вакансія</span>` |
| `≥ 640px` (sm+) | SVG «+» + «Нова вакансія» | span видимий |

#### Іконка

SVG «плюс» (`M12 4v16m8-8H4`), `16×16px`, `stroke-width: 2.5`, колір успадковується (`stroke="currentColor"`).

---

### CSS-змінні (Design Tokens)

| CSS-змінна | Елемент | Light | Dark |
|-----------|---------|-------|------|
| `--bg-main` | Фон сторінки | `bg-main.webp` (repeat, fixed) | `#111827` |
| `--form-bg` | Фон форми / контейнера | `#F3F4F6` | `#1F2937` |
| `--input-bg` | Фон поля вводу | `#FFFFFF` | `#111827` |
| `--header-bg` | Фон шапки | `#FFFFFF` | `#111827` |
| `--header-border` | Лінія під шапкою | `#E5E7EB` | `#374151` |
| `--text-main` | Основний текст | `#111827` | `#E5E7EB` |
| `--nav-link` | Посилання навігації | `#374151` | `#E5E7EB` |
| `--nav-active` | Активне посилання | `#111827` | `#FFFFFF` |
| `--nav-hover` | Hover-стан навігації | `#F36F21` | `#F36F21` |
| `--info-link` | Інформаційне посилання | `#2563EB` | `#60A5FA` |
| `--input-border-color` | Рамка поля вводу | `#D1D5DB` | `#4B5563` |
| `--input-focus-color` | Рамка фокусу | `#F36F21` | `#F36F21` |
| `--input-focus-style` | Товщина рамки фокусу | `2px solid` | `2px solid` |
| `--input-icon` | Іконка у полі вводу | `#6B7280` | `#9CA3AF` |
| `--input-placeholder` | Текст-підказка | `#9CA3AF` | `#6B7280` |
| `--input-radius` | Радіус поля вводу | `8px` | `8px` |
| `--form-radius` | Радіус форми | `12px` | `12px` |
| `--form-border-color` | Рамка форми | `#E5E7EB` | `#374151` |
| `--btn-pri-bg` | Фон Primary-кнопки | `#F36F21` | `#F36F21` |
| `--btn-pri-text` | Текст Primary-кнопки | `#FFFFFF` | `#FFFFFF` |
| `--btn-gho-text` | Текст Ghost-кнопки | `#374151` | `#E5E7EB` |
| `--btn-gho-border` | Рамка Ghost-кнопки | `#D1D5DB` | `#4B5563` |
| `--check-bg` | Фон чекбоксу | `#FFFFFF` | `#2D3748` |
| `--check-border` | Рамка чекбоксу | `#D1D5DB` | `#4B5563` |
| `--check-active-bg` | Фон активного чекбоксу | `#F36F21` | `#F36F21` |
| `--check-icon-color` | Колір галочки | `#FFFFFF` | `#FFFFFF` |
| `--check-label-color` | Текст мітки чекбоксу | `#374151` | `#E5E7EB` |
| `--select-arrow` | Стрілка select | `#374151` | `#9CA3AF` |
| `--dropdown-bg` | Фон dropdown-меню | `#FFFFFF` | `#1F2937` |
| `--dropdown-hover` | Hover у dropdown | `#F3F4F6` | `#374151` |
| `--error-border` | Рамка помилки | `#DC2626` | `#EF4444` |
| `--required-color` | Зірочка обов'язкового поля | `#EF4444` | `#F87171` |
| `--hot-icon` | Іконка "гарячої" вакансії | `#F36F21` | `#F36F21` |
| `--top-icon` | Іконка TOP-вакансії | `#FACC15` | `#FACC15` |
| `--logo-primary` | Акцентний колір логотипу | `#F36F21` | `#F36F21` |
| `--logo-secondary` | Темний колір логотипу | `#343741` | `#FFFFFF` |
| `--h1-text-color` | H1 на сторінці роботодавців | `#60A5FA` | `#60A5FA` |

---

### Картка вакансії (`.mj-card`)

| Тип | Фон (light) | Рамка (light) |
|-----|-------------|---------------|
| Звичайна | `#FFFFFF` | `#A7A7A7` |
| Featured / HOT | `#FFFBEB` | `#FCD34D` |
| Top | `#D2D2D2` | `#E8962E` |
| Hover-стан | — | `#3B82F6` + shadow |

---

### Бейджі типів зайнятості

| Value | Назва | Колір бейджу |
|-------|-------|-------------|
| `full-time` | Повна зайнятість | `#10B981` (green) |
| `part-time` | Часткова | `#EC4899` (pink) |
| `remote` | Віддалено | `#3B82F6` (blue) |
| `hybrid` | Гібрид | `#F59E0B` (amber) |
| `contract` | Контракт | `#8B5CF6` (violet) |

---

### Бейджі статусів заявок

| Value | Назва | Колір |
|-------|-------|-------|
| `pending` | Новий | Gray |
| `screening` | Розгляд | `#3B82F6` |
| `interview` | Співбесіда | `#F59E0B` |
| `hired` | Прийнятий | `#10B981` |
| `rejected` | Відхилений | `#EF4444` |
| `withdrawn` | Відкликано | Orange |

---

### Toast-сповіщення

| Тип | Фон | Текст | Рамка |
|-----|-----|-------|-------|
| Success | `#F0FDF4` | `#15803D` | `#BBF7D0` |
| Error | `#FEF2F2` | `#B91C1C` | `#FECACA` |
| Warning | `#FFFBEB` | `#92400E` | `#FCD34D` |
| Info | `#EFF6FF` | `#1D4ED8` | `#BFDBFE` |

---

### Типографіка

| Елемент | Розмір | Font-weight | Колір (light) |
|---------|--------|-------------|---------------|
| H1 (заголовок вакансії) | 28px | 700 | `#1F2937` |
| H2 (секція) | 20px | 700 | `#1F2937` |
| Body | 16px | 400 | `#1F2937` |
| Label | 14px | 500 | `#1F2937` |
| Small / Meta | 12px | 400 | `#6B7280` |
| Caption | 11px | 600 | `#6B7280` |
| **Шрифт** | `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif` | — | — |

---

### Іконки

- Бібліотека: **Heroicons** (inline SVG)
- Розміри: `16px` (sm) / `20px` (md) / `24px` (lg)
- Stroke-width: `2px` (звичайний), `2.5px` (акцентний)

---

### Стани полів форм

| Стан | Border | Background |
|------|--------|------------|
| Default | `#D1D5DB` | `#FFFFFF` |
| Focus | `#F36F21` (2px) | `#FFFFFF` |
| Error | `#EF4444` | `#FFFFFF` |
| Disabled | `#E5E7EB` | `#F9FAFB` |
| Placeholder | — | текст `#9CA3AF` |

---

### Спейсинг і сітка

| Токен | Значення |
|-------|----------|
| `xs` | 4px |
| `sm` | 8px |
| `md` | 12px |
| `lg` | 16px |
| `xl` | 20px |
| `2xl` | 24px |
| `3xl` | 32px |
| `4xl` | 40px |
| Container `max-width` | `1280px` (`max-w-7xl`) |
| Breakpoints | `sm: 640px` / `md: 768px` / `lg: 1024px` / `xl: 1280px` |

---

### Анімації

| Назва | Тривалість | Easing |
|-------|-----------|--------|
| Fast | 0.2s | ease-in-out |
| Normal | 0.3s | ease-in-out |
| Slow | 0.5s | ease-in-out |
| Hover lift | `translateY(-4px)` | — |
| Modal slide-up | 0.3s | — |

---

### Контейнери (light theme)

| Елемент | Фон | Рамка |
|---------|-----|-------|
| Hero / Search container (index) | `#D2D2D2` | — |
| Filter panel / aside | `#D2D2D2` | — |
| Seeker / Employer tabs header | `#D2D2D2` | — |
| Employer dashboard card | `#FFFFFF` | `3px solid #D1D5DB` |
| Login card (`.login-card`) | `#FFFFFF` | `1px solid #A7A7A7` |
| Continue with Google button | `#FFFFFF` | `1px solid #A7A7A7` |

---

### Логотип

| Параметр | Значення |
|---------|---------|
| Основний файл | `mj-logo.png` / `mj-logo.webp` |
| Dark-версія | `mj-logo-dark-theme.webp` |
| Favicon | `favicon.ico` (64×64, WebP) |
| Мінімальний розмір | 100×100px |
| Accent color | `#F36F21` |
| Clear space | мінімум 16px з усіх сторін |
| На темному фоні | використовувати `*-dark-theme.webp` |

---

### Фонові зображення

| Сторінка | Файл | Параметри |
|---------|------|-----------|
| `/` (index) | `/img/bg-main.webp` | repeat, auto size, fixed |
| `/dashboard/seeker` | `/img/bg-main.webp` | repeat, auto size, fixed |
| `/dashboard/employer` | `/img/bg-main.webp` | repeat, auto size, fixed |
| `/login` | `/img/bg-main.webp` | repeat, auto size, fixed |
| Dark theme (всі) | — | `bg-gray-900` (`#111827`) |

---

*Оновлено: 2026-05-09*

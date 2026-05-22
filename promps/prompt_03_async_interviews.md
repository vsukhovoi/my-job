Увага: у проєкті використовується SkillTag (не Skill) — модель для навичок. Не створювати нічого пов'язаного з Skill.

# Завдання: Асинхронні текстові інтерв'ю

## Контекст проєкту
Платформа My Job (myjob.co.ua). Laravel 13, Livewire 3 (Volt components), Filament, PostgreSQL.
Ролі: `UserRole::Candidate` та `UserRole::Employer` (Enum).
PHPUnit 12 — атрибути `#[Test]`.
`$this->actingAs()` викликається ДО `Volt::test()`.

---

## Що реалізувати

### 1. Migrations

**`interview_requests`**:
```
id
application_id    — FK → applications, cascadeOnDelete
employer_user_id  — FK → users
questions         — json  (['Питання 1', 'Питання 2', ...])
deadline_at       — timestamp, nullable
status            — string, default 'pending'
timestamps
```

**`interview_responses`**:
```
id
interview_request_id — FK → interview_requests, cascadeOnDelete
user_id              — FK → users, cascadeOnDelete  (кандидат)
answers              — json  ([{question_index: 0, text: '...'}, ...])
submitted_at         — timestamp, nullable
timestamps
```

### 2. Enum: `InterviewRequestStatus`

Файл: `app/Enums/InterviewRequestStatus.php`:

```php
enum InterviewRequestStatus: string
{
    case Pending  = 'pending';   // надіслано, кандидат не відповів
    case Answered = 'answered';  // кандидат відповів
    case Expired  = 'expired';   // дедлайн минув без відповіді

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Очікує відповіді',
            self::Answered => 'Відповів',
            self::Expired  => 'Термін минув',
        };
    }
}
```

### 3. Моделі

**`InterviewRequest`**:
- Cast: `'status' => InterviewRequestStatus::class`, `'questions' => 'array'`, `'deadline_at' => 'datetime'`
- Relationships: `application()`, `employer()`, `response()`
- Scope: `pending()`, `answered()`, `expired()`
- Метод `isExpired(): bool` — перевіряє `deadline_at < now()`

**`InterviewResponse`**:
- Cast: `'answers' => 'array'`, `'submitted_at' => 'datetime'`
- Relationships: `interviewRequest()`, `candidate()`
- Метод `isSubmitted(): bool`

### 4. Service: `InterviewService`

Файл: `app/Services/InterviewService.php`

**`send(Application $application, array $questions, ?Carbon $deadline): InterviewRequest`**
- Перевіряє що для цього `application` ще немає активного `InterviewRequest`
- Створює `InterviewRequest`
- Змінює статус `Application` на `Interview`
- Диспатчить `InterviewRequestSent` event

**`saveResponse(InterviewRequest $request, User $candidate, array $answers, bool $submit = false): InterviewResponse`**
- Створює або оновлює `InterviewResponse` (upsert по `interview_request_id`)
- Якщо `$submit = true` → встановлює `submitted_at = now()`, статус `InterviewRequest` → `Answered`
- Диспатчить `InterviewResponseSubmitted` event якщо `$submit`

**`markExpired(): int`**
- Знаходить всі `pending` запити з `deadline_at < now()`
- Масово оновлює статус на `Expired`
- Повертає кількість оновлених записів

### 5. Command: `interviews:mark-expired`

Файл: `app/Console/Commands/MarkExpiredInterviews.php`
- Викликає `InterviewService::markExpired()`
- Реєструє в `routes/console.php` або `Kernel.php` з розкладом `->daily()`

### 6. Events

**`InterviewRequestSent`** → передає `InterviewRequest`
**`InterviewResponseSubmitted`** → передає `InterviewResponse`

Обидва Listener-и — заглушки з TODO для email + Telegram сповіщень.

### 7. Volt-компонент: `employer.interview-request-form`

Файл: `resources/views/livewire/employer/interview-request-form.blade.php`

Props: `$applicationId`

Відображає:
- Заголовок «Надіслати інтерв'ю»
- Динамічний список питань:
  - Мінімум 1, максимум 5 питань
  - Кожне питання — textarea
  - Кнопки «+ Додати питання» та «× Видалити» (якщо питань > 1)
- Datepicker для дедлайну (необов'язково)
- Кнопки «Скасувати» / «Надіслати інтерв'ю»
- Валідація: кожне питання не менше 10 символів
- Якщо `InterviewRequest` вже існує → показати статус замість форми

### 8. Volt-компонент: `candidate.interview-response-form`

Файл: `resources/views/livewire/candidate/interview-response-form.blade.php`

Props: `$interviewRequestId`

Поведінка:
- Завантажує `InterviewRequest` та існуючий чернетковий `InterviewResponse`
- Показує питання по одному (крок 1 з N)
  - Textarea для відповіді (max 1000 символів, лічильник)
  - Кнопки «← Назад» / «Далі →»
  - При переході — авто-зберігає чернетку (`submit = false`)
- На останньому кроці — екран підтвердження:
  - Перелік всіх питань і відповідей
  - Кнопка «Змінити» біля кожного
  - Кнопки «Зберегти чернетку» / «Надіслати ✓»
- Якщо `deadline_at` є — показує «⏳ Залишилось X днів»
- Якщо вже `submitted` → показати відповіді в режимі read-only

### 9. Volt-компонент: `employer.interview-response-view`

Файл: `resources/views/livewire/employer/interview-response-view.blade.php`

Props: `$interviewRequestId`

Відображає:
- Ім'я кандидата та дату відповіді
- Кожне питання + відповідь кандидата
- Кнопки «Відхилити» / «Перевести на інтерв'ю»
  - Обидві змінюють статус `Application` через `ApplicationStatus`

### 10. Filament Resource: `InterviewRequestResource`

- Тільки читання (для адміністратора)
- Таблиця: application → кандидат, вакансія, статус, deadline_at
- Фільтр по статусу

---

## Тести

Створи `tests/Feature/Interview/InterviewServiceTest.php`:

```php
#[Test]
public function employer_can_send_interview_request(): void

#[Test]
public function cannot_send_duplicate_interview_request(): void

#[Test]
public function candidate_can_save_draft_response(): void

#[Test]
public function candidate_can_submit_response(): void

#[Test]
public function submitted_response_changes_request_status_to_answered(): void

#[Test]
public function expired_interviews_are_marked_correctly(): void

#[Test]
public function candidate_cannot_edit_submitted_response(): void
```

---

## Чого НЕ робити
- Не реалізовувати відео-відповіді — тільки текстовий формат
- Не чіпати існуючі міграції
- Не використовувати рядкові ролі — тільки UserRole Enum
- Не використовувати стандартні Livewire компоненти — тільки Volt
- Не додавати платіжну логіку

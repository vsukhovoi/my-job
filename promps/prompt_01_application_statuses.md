# Завдання: Розширені статуси відгуку

## Контекст проєкту
Платформа My Job (myjob.co.ua). Laravel 13, Livewire 3 (Volt components), Filament, PostgreSQL.
Ролі: `UserRole::Candidate` та `UserRole::Employer` (Enum, не рядки).
PHPUnit 12 — атрибути `#[Test]`, не docblock `@test`.
`$this->actingAs()` викликається ДО `Volt::test()`, не в ланцюжку.

---

## Що реалізувати

### 1. Migration: `application_status_logs`

Створи міграцію для таблиці `application_status_logs`:

```
id
application_id  — FK → applications, cascadeOnDelete
status          — string
changed_by      — FK → users, nullable (null = система)
comment         — text, nullable
created_at      — timestamp (без updated_at)
```

### 2. Enum: `ApplicationStatus`

Створи `app/Enums/ApplicationStatus.php`:

```php
enum ApplicationStatus: string
{
    case Pending   = 'pending';
    case Viewed    = 'viewed';
    case Reviewing = 'reviewing';
    case Interview = 'interview';
    case Offered   = 'offered';
    case Rejected  = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Надіслано',
            self::Viewed    => 'Переглянуто',
            self::Reviewing => 'На розгляді',
            self::Interview => 'Інтерв\'ю',
            self::Offered   => 'Оффер',
            self::Rejected  => 'Відмовлено',
            self::Withdrawn => 'Відкликано',
        };
    }
}
```

### 3. Оновлення моделі `Application`

- Додати cast: `'status' => ApplicationStatus::class`
- Додати relationship: `statusLogs()` → hasMany `ApplicationStatusLog`
- Додати метод `logStatus(ApplicationStatus $status, ?User $changedBy = null, ?string $comment = null)` — створює запис у `application_status_logs`

### 4. Модель `ApplicationStatusLog`

Створи `app/Models/ApplicationStatusLog.php`:
- `$timestamps = false` (тільки `created_at`)
- Cast: `'status' => ApplicationStatus::class`
- Relationship: `changedBy()` → belongsTo User, nullable

### 5. Event + Listener: `ApplicationStatusChanged`

- Event: `app/Events/ApplicationStatusChanged.php` — передає `Application` і новий `ApplicationStatus`
- Listener: `app/Listeners/NotifyApplicationStatusChanged.php` — заглушка з TODO для відправки email + Telegram сповіщення
- Зареєструвати в `EventServiceProvider`

### 6. Volt-компонент: `candidate.application-timeline`

Файл: `resources/views/livewire/candidate/application-timeline.blade.php`

Props: `$applicationId`

Відображає:
- Назву вакансії та компанії
- Горизонтальний таймлайн із кроків: Надіслано → Переглянуто → На розгляді → Інтерв'ю → Оффер/Відмова
  - Заповнений круг `●` + дата — для пройдених статусів
  - Порожній круг `○` — для майбутніх (сірий)
- Список лог-записів знизу (дата + статус + коментар якщо є)
- Кнопку «Відкликати відгук» якщо статус `Pending` або `Viewing`

### 7. Volt-компонент: `employer.application-status-form`

Файл: `resources/views/livewire/employer/application-status-form.blade.php`

Props: `$applicationId`

Відображає:
- Select зі статусами (`Reviewing`, `Interview`, `Offered`, `Rejected`)
- Textarea для коментаря (необов'язково)
- Кнопку «Зберегти»
- При збереженні: викликає `$application->logStatus()`, диспатчить `ApplicationStatusChanged`

### 8. Авто-перехід у статус `Viewed`

У методі показу картки кандидата роботодавцем (або в Volt-компоненті перегляду):
- Якщо поточний статус `Pending` і переглядає роботодавець → автоматично змінити на `Viewed` + записати лог (changedBy = null)

---

## Тести

Створи `tests/Feature/Employer/ApplicationStatusTest.php`:

```php
#[Test]
public function employer_can_change_application_status(): void

#[Test]
public function status_change_is_logged(): void

#[Test]
public function application_auto_transitions_to_viewed(): void

#[Test]
public function candidate_can_withdraw_application(): void

#[Test]
public function candidate_cannot_withdraw_after_interview(): void
```

Використовуй `RefreshDatabase`, `actingAs()` перед `Volt::test()`, фабрики для даних.

---

## Чого НЕ робити
- Не використовувати рядкові ролі (`'employer'`, `'candidate'`) — тільки `UserRole` Enum
- Не використовувати стандартні Livewire компоненти — тільки Volt
- Не додавати платіжну логіку — вона реалізується окремо
- Не чіпати існуючі міграції — тільки нові файли

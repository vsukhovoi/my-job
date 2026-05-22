# Claude Code Prompt: Bidirectional Status Sync

## Контекст проекту

Платформа **My Job** — Laravel 11+, Livewire 3, Volt-компоненти, PHPUnit 12.
Вже існують:
- `App\Enums\UserRole` (значення: `UserRole::Candidate`, `UserRole::Employer`)
- `App\Enums\ApplicationStatus` (якщо немає — створити згідно специфікації нижче)
- Таблиця `applications` з полем `user_id` (не `seeker_id`)
- Тести у `tests/Feature/Seeker/` — не чіпати

---

## Завдання

Реалізувати модуль **bidirectional sync статусів заявок** між Seeker і Employer кабінетами.

**Не змінювати нічого** поза переліком файлів нижче. Перед кожним кроком — підтвердження.

---

## Крок 1 — Enum та міграції

**Підтвердь перед виконанням.**

### 1а. Оновити або створити `App\Enums\ApplicationStatus`

```php
<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending   = 'pending';
    case Reviewing = 'reviewing';
    case Invited   = 'invited';
    case Declined  = 'declined';
    case Withdrawn = 'withdrawn';
    case Hired     = 'hired';

    public function allowedActors(): array
    {
        return match($this) {
            self::Reviewing, self::Invited, self::Hired => ['employer'],
            self::Withdrawn                             => ['seeker'],
            self::Declined                              => ['employer', 'seeker'],
            default                                     => [],
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Очікує розгляду',
            self::Reviewing => 'Розглядається',
            self::Invited   => 'Запрошено на інтерв\'ю',
            self::Declined  => 'Відхилено',
            self::Withdrawn => 'Відкликано',
            self::Hired     => 'Прийнято',
        };
    }
}
```

### 1б. Міграція `application_status_history`

Створити міграцію `create_application_status_history_table`:

```php
Schema::create('application_status_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('application_id')->constrained()->cascadeOnDelete();
    $table->string('from_status')->nullable();
    $table->string('to_status');
    $table->foreignId('changed_by')->constrained('users');
    $table->string('actor_role'); // 'employer' | 'seeker'
    $table->text('comment')->nullable();
    $table->timestamps();
});
```

### 1в. Модель `ApplicationStatusHistory`

```
app/Models/ApplicationStatusHistory.php
```

- `fillable`: `application_id`, `from_status`, `to_status`, `changed_by`, `actor_role`, `comment`
- cast `from_status` і `to_status` → `ApplicationStatus`
- belongs to `Application`
- belongs to `User` (через `changed_by`)

### 1г. Оновити модель `Application`

Додати:
- `$casts['status'] => ApplicationStatus::class`
- `hasMany(ApplicationStatusHistory::class)`

---

## Крок 2 — Exception та Service

**Підтвердь перед виконанням.**

### 2а. Exception

```
app/Exceptions/UnauthorizedStatusChangeException.php
```

Розширює `\Exception`, повідомлення: `'Actor is not allowed to set this status.'`

### 2б. `ApplicationStatusService`

```
app/Services/ApplicationStatusService.php
```

```php
public function changeStatus(
    Application $application,
    ApplicationStatus $newStatus,
    User $actor,
    string $actorRole,  // 'employer' | 'seeker'
    ?string $comment = null
): Application
```

Логіка:
1. Перевірити `in_array($actorRole, $newStatus->allowedActors())` — якщо ні, кинути `UnauthorizedStatusChangeException`
2. Зберегти `$oldStatus = $application->status`
3. У `DB::transaction`:
   - `$application->update(['status' => $newStatus])`
   - `ApplicationStatusHistory::create([...])` з усіма полями
   - `event(new ApplicationStatusChanged($application, $oldStatus, $newStatus, $actor))`
4. Повернути `$application->refresh()`

---

## Крок 3 — Event та Listeners

**Підтвердь перед виконанням.**

### 3а. Event `ApplicationStatusChanged`

```
app/Events/ApplicationStatusChanged.php
```

- implements `ShouldBroadcast`
- constructor: `Application $application, ApplicationStatus $oldStatus, ApplicationStatus $newStatus, User $changedBy`
- всі властивості `public`
- `broadcastOn()` повертає два `PrivateChannel`:
  - `"seeker.{$this->application->user_id}"`
  - `"employer.{$this->application->job->employer_id}"`

> Якщо відносини `job` або поле `employer_id` називаються інакше — перевірити схему БД і адаптувати назви. **Не вигадувати.**

### 3б. Listener `SendStatusNotification`

```
app/Listeners/SendStatusNotification.php
```

- `handle(ApplicationStatusChanged $event)`
- Надіслати `Notification` обом сторонам (Seeker і Employer) через `Notification::send()`
- Notification клас: `App\Notifications\ApplicationStatusChangedNotification`
  - канали: `['mail', 'database']`
  - mail subject: `"Статус заявки змінено: {$event->newStatus->label()}"`
  - `toArray`: `['application_id', 'old_status', 'new_status', 'changed_by_role']`

### 3в. Listener `BroadcastToLivewire`

```
app/Listeners/BroadcastToLivewire.php
```

```php
public function handle(ApplicationStatusChanged $event): void
{
    \Livewire\Livewire::dispatch(
        'application-status-updated',
        applicationId: $event->application->id,
        newStatus: $event->newStatus->value,
    );
}
```

### 3г. Реєстрація у `EventServiceProvider`

```php
ApplicationStatusChanged::class => [
    SendStatusNotification::class,
    BroadcastToLivewire::class,
],
```

---

## Крок 4 — Volt-компоненти

**Підтвердь перед виконанням.**

### 4а. Seeker — оновити існуючий компонент трекера заявок

Файл: знайти існуючий Volt-компонент у `resources/views/livewire/seeker/` що відповідає за список заявок.

Додати `on`-обробник (не замінювати існуючу логіку):

```php
use App\Enums\ApplicationStatus;
use function Livewire\Volt\on;

on(['application-status-updated' => function (int $applicationId, string $newStatus) {
    $this->applications = $this->applications->map(function ($app) use ($applicationId, $newStatus) {
        if ($app->id === $applicationId) {
            $app->status = ApplicationStatus::from($newStatus);
        }
        return $app;
    });
}]);
```

### 4б. Employer — аналогічно для компонента списку заявок

Файл: знайти існуючий Volt-компонент у `resources/views/livewire/employer/`.

Якщо компонента ще немає — **зупинитись і повідомити**, не створювати самостійно.

---

## Крок 5 — PHPUnit тести

**Підтвердь перед виконанням.**

Файл: `tests/Feature/Sync/BidirectionalStatusSyncTest.php`

Використовувати синтаксис **PHPUnit 12**: атрибут `#[Test]`, не docblock `/** @test */`.
`actingAs()` викликати **до** будь-яких Volt::test() якщо потрібно.

### Тести для написання:

```
1. employer_can_change_status_to_invited
2. seeker_can_withdraw_application
3. employer_cannot_withdraw_application          ← очікує UnauthorizedStatusChangeException
4. seeker_cannot_set_status_to_hired             ← очікує UnauthorizedStatusChangeException
5. status_change_is_recorded_in_history
6. status_change_dispatches_event
7. both_sides_receive_notification_on_change
```

Шаблон для кожного тесту:

```php
#[Test]
public function employer_can_change_status_to_invited(): void
{
    $seeker   = User::factory()->create(['role' => UserRole::Candidate]);
    $employer = User::factory()->create(['role' => UserRole::Employer]);
    $job      = Job::factory()->for($employer)->create();
    $app      = Application::factory()
                    ->for($seeker, 'user')
                    ->for($job)
                    ->create(['status' => ApplicationStatus::Pending]);

    $this->actingAs($employer);

    $service = $this->app->make(ApplicationStatusService::class);
    $service->changeStatus($app, ApplicationStatus::Invited, $employer, 'employer');

    expect($app->refresh()->status)->toBe(ApplicationStatus::Invited);
}
```

> Якщо фабрики `Job` або `Application` мають інші назви або відносини — перевірити існуючі фабрики у `database/factories/` і адаптувати. **Не створювати нові фабрики без запиту.**

---

## Обмеження

- Не змінювати файли у `tests/Feature/Seeker/`
- Не перейменовувати існуючі поля у таблиці `applications`
- Не замінювати існуючу логіку у Volt-компонентах — лише додавати `on`-обробник
- Якщо якийсь файл не існує або схема відрізняється від очікуваної — **зупинитись і запитати**, не вигадувати структуру

---

## Очікуваний результат

```
✓ php artisan migrate — без помилок
✓ php artisan test tests/Feature/Sync/ — усі 7 тестів зелені
✓ Існуючі тести tests/Feature/Seeker/ — залишаються зеленими
```

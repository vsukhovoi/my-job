# Claude Code Prompt: VacancyService + expires_at + slug

## Контекст проекту

Платформа **My Job** — Laravel 11+, Livewire 3, Volt-компоненти, PHPUnit 12.
Таблиця вакансій: `vacancies`. Існуючий `save()` у Volt робить `Vacancy::create()` / `->update()` напряму.
Існують: `SubscriptionService`, `PlanType` enum, `EmployerSubscription`.

---

## Завдання

Три виправлення в порядку виконання. Кожне — окремий крок з підтвердженням.

---

## Крок 1 — `VacancyService`

**Підтвердь перед виконанням.**

Файл: `app/Services/VacancyService.php`

### Методи:

```php
public function publish(User $employer, array $data): Vacancy
public function update(Vacancy $vacancy, array $data): Vacancy
public function getExpiresAt(User $employer): Carbon
public function generateSlug(string $title, ?int $excludeId = null): string
```

### Логіка `getExpiresAt()`:

```php
public function getExpiresAt(User $employer): Carbon
{
    $plan = $employer->currentPlan();

    return match($plan?->type) {
        PlanType::Business, PlanType::Pro => now()->addDays(60),
        default => now()->addDays(30), // Free, Start, null
    };
}
```

### Логіка `generateSlug()`:

```php
public function generateSlug(string $title, ?int $excludeId = null): string
{
    $base = Str::slug($title);
    $slug = $base;
    $counter = 1;

    while (
        Vacancy::where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()
    ) {
        $slug = "{$base}-{$counter}";
        $counter++;
    }

    return $slug;
}
```

### Логіка `publish()`:

1. Згенерувати slug через `generateSlug($data['title'])`
2. Встановити `expires_at` через `getExpiresAt($employer)`
3. Встановити `published_at = now()` якщо не передано
4. Встановити `status` якщо не передано — `VacancyStatus::Active`
5. `return Vacancy::create([...$data, 'slug' => $slug, 'expires_at' => $expiresAt])`

### Логіка `update()`:

1. Якщо `$data['title']` відрізняється від `$vacancy->title` — перегенерувати slug через `generateSlug($data['title'], $vacancy->id)`
2. Не змінювати `expires_at` при оновленні (тільки при першій публікації)
3. `$vacancy->update([...$data, 'slug' => $slug])`
4. `return $vacancy->refresh()`

> Перевірити чи існує поле `slug` у таблиці `vacancies`. Якщо немає — **зупинитись і повідомити**, не додавати міграцію самостійно.

> Перевірити чи існує поле `expires_at` у таблиці `vacancies`. Якщо немає — **зупинитись і повідомити**.

> Перевірити як називається Enum статусів вакансії (`VacancyStatus`?) і які значення має. Адаптувати під існуючий Enum, **не створювати новий**.

---

## Крок 2 — Підключити `VacancyService` у Volt

**Підтвердь перед виконанням.**

Знайти існуючий Volt-компонент створення/редагування вакансії (той де є `save()`).

Замінити `Vacancy::create($data)` і `->update($data)` напряму на виклики через `VacancyService`:

```php
// Замість Vacancy::create($data)
$vacancy = $this->vacancyService->publish(auth()->user(), $data);

// Замість $vacancy->update($data)
$this->vacancyService->update($vacancy, $data);
```

Підключити сервіс через `inject()` або `mount()` — відповідно до стилю існуючих компонентів у проекті.

**Не змінювати** валідацію, перевірку ліміту `canPublishJob()`, редирект та інші частини `save()`.

---

## Крок 3 — Міграція (якщо потрібна)

**Підтвердь перед виконанням.**

> Цей крок виконується **тільки якщо** на кроці 1 виявилось що `expires_at` або `slug` відсутні у таблиці `vacancies`.

Якщо обидва поля є — крок пропустити і повідомити.

Якщо якогось поля немає — створити міграцію `add_missing_fields_to_vacancies_table`:

```php
// expires_at якщо відсутнє
$table->timestamp('expires_at')->nullable()->after('published_at');

// slug якщо відсутнє
$table->string('slug')->nullable()->after('title');
// після додавання — заповнити існуючі записи:
// Vacancy::each(fn($v) => $v->update(['slug' => Str::slug($v->title)]));
```

---

## Крок 4 — PHPUnit тести

**Підтвердь перед виконанням.**

Файл: `tests/Feature/Vacancy/VacancyServiceTest.php`

Синтаксис: `#[Test]`, не `/** @test */`.

```
1. publish_sets_expires_at_30_days_for_free_employer
2. publish_sets_expires_at_60_days_for_business_employer
3. publish_sets_expires_at_60_days_for_pro_employer
4. publish_generates_unique_slug_from_title
5. publish_adds_suffix_when_slug_already_exists
6. update_regenerates_slug_when_title_changes
7. update_keeps_slug_when_title_unchanged
8. update_does_not_change_expires_at
```

Шаблон:

```php
#[Test]
public function publish_sets_expires_at_30_days_for_free_employer(): void
{
    $employer = User::factory()->create(['role' => UserRole::Employer]);
    // null підписка = Free (1 безкоштовна вакансія)

    $service = $this->app->make(VacancyService::class);
    $vacancy = $service->publish($employer, [
        'title'           => 'Тестова вакансія',
        'description'     => str_repeat('а', 50),
        'employment_type' => 'full_time',
        'category_id'     => 1,
        'city_id'         => 1,
        'salary'          => 10000,
        'currency'        => 'UAH',
    ]);

    expect($vacancy->expires_at->diffInDays(now()))->toBe(30);
}
```

```php
#[Test]
public function publish_adds_suffix_when_slug_already_exists(): void
{
    $employer = User::factory()->create(['role' => UserRole::Employer]);
    $service  = $this->app->make(VacancyService::class);

    $data = [
        'title'       => 'Повар',
        'description' => str_repeat('а', 50),
        // ... інші обов'язкові поля
    ];

    $first  = $service->publish($employer, $data);
    $second = $service->publish($employer, $data);

    expect($first->slug)->toBe('povar');
    expect($second->slug)->toBe('povar-1');
}
```

> Якщо фабрика `Vacancy` або категорії/міста вимагають існуючих записів у БД — використати `RefreshDatabase` trait і створити необхідні записи в `setUp()`. **Не вигадувати ID що не існують.**

---

## Обмеження

- Не змінювати валідацію, `canPublishJob()`, редирект у існуючому `save()`
- Не змінювати файли у `tests/Feature/Seeker/`, `tests/Feature/Sync/`, `tests/Feature/Billing/`
- Таблиця вакансій: `vacancies`
- Якщо поле або Enum не знайдено — **зупинитись і повідомити**, не вигадувати

---

## Очікуваний результат

```
✓ php artisan migrate — без помилок (або "nothing to migrate" якщо поля вже є)
✓ php artisan test tests/Feature/Vacancy/ — 8/8 зелені
✓ Існуючі тести — залишаються зеленими
✓ Нова вакансія отримує expires_at, унікальний slug, через VacancyService
```

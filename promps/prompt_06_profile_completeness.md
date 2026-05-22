# Завдання: Індикатор заповненості профілів

## Контекст проєкту
Платформа My Job (myjob.co.ua). Laravel 13, Livewire 3 (Volt components), Blade, PostgreSQL.
Ролі: `UserRole::Candidate` та `UserRole::Employer` (Enum).
PHPUnit 12 — атрибути `#[Test]`.
`$this->actingAs()` викликається ДО `Volt::test()`.

Перед початком: перевір реальну структуру моделей `User`, `CandidateProfile` (або аналог), `EmployerProfile` (або аналог), `Vacancy` — назви полів можуть відрізнятись. Адаптуй під фактичні поля.

---

## Що реалізувати

### 1. Service: `ProfileCompletenessService`

Файл: `app/Services/ProfileCompletenessService.php`

Три публічних методи. Кожен повертає масив:
```php
[
    'score'       => 75,           // int 0–100
    'next_step'   => [
        'label' => 'Додайте фото',
        'url'   => '/candidate/profile#photo',
    ],                             // null якщо score = 100
    'missing'     => [             // список незаповнених полів
        ['field' => 'photo', 'label' => 'Фото профілю', 'weight' => 10],
    ],
]
```

---

#### `candidateScore(User $user): array`

Поля та ваги (разом 100):

| Поле | Вага |
|------|------|
| Ім'я та прізвище | 10 |
| Фото | 10 |
| Місто | 5 |
| Очікувана зарплата | 5 |
| Досвід роботи (хоча б один запис) | 20 |
| Освіта (хоча б один запис) | 10 |
| Навички — SkillTag (хоча б 3) | 15 |
| Про себе / summary | 10 |
| Контактний телефон | 5 |
| Завантажене резюме (PDF) | 10 |

`next_step` — перше незаповнене поле з найбільшою вагою.

---

#### `employerScore(User $user): array`

Поля та ваги (разом 100):

| Поле | Вага |
|------|------|
| Назва компанії | 15 |
| Логотип | 10 |
| Сфера діяльності | 10 |
| Опис компанії | 20 |
| Веб-сайт | 10 |
| Місто / адреса | 10 |
| Кількість співробітників | 5 |
| Контактний email | 10 |
| Телефон | 5 |
| Посилання на соцмережі (хоча б одне) | 5 |

---

#### `vacancyScore(Vacancy $vacancy): array`

Поля та ваги (разом 100):

| Поле | Вага |
|------|------|
| Назва вакансії | 15 |
| Опис (мін. 200 символів) | 20 |
| Зарплата (хоча б salary_from) | 15 |
| Місто або remote | 10 |
| Тип зайнятості | 10 |
| Навички — SkillTag (хоча б одна) | 15 |
| Категорія | 10 |
| Контактна особа або email | 5 |

---

### 2. Volt-компонент: `shared.profile-completeness`

Файл: `resources/views/livewire/shared/profile-completeness.blade.php`

**Універсальний компонент** — працює для трьох типів профілів.

Props: `$type` (`'candidate'` | `'employer'` | `'vacancy'`), `$modelId` (int, ID вакансії для типу `vacancy`)

Логіка:
- Викликає відповідний метод `ProfileCompletenessService`
- Для `candidate` та `employer` — використовує `auth()->user()`
- Для `vacancy` — завантажує `Vacancy::find($modelId)`

**UI:**

```
┌─────────────────────────────────────────────────┐
│ Заповненість профілю                            │
│                                                 │
│  ████████████████░░░░  75%                      │
│                                                 │
│ ✅ Наступний крок:                              │
│    Додайте фото профілю → [Заповнити]           │
│                                                 │
│ Що ще бракує:          [Показати ▾]             │
│  · Навички (15 балів)                           │
│  · Резюме PDF (10 балів)                        │
│  · Телефон (5 балів)                            │
└─────────────────────────────────────────────────┘
```

Деталі UI:
- Прогрес-бар з кольором залежно від score:
  - `< 40` → червоний
  - `40–74` → жовтий
  - `≥ 75` → зелений
- «Що ще бракує» — колапсований список, розкривається кліком
- При `score = 100` — показати «✅ Профіль заповнений повністю» без next_step
- Кнопка «Заповнити» — посилання на відповідну секцію профілю

---

### 3. Підключення у кабінетах

**Кабінет кандидата** (бокова панель або дашборд):
```blade
<livewire:shared.profile-completeness type="candidate" />
```

**Кабінет роботодавця** (бокова панель або дашборд):
```blade
<livewire:shared.profile-completeness type="employer" />
```

**Сторінка редагування вакансії** (над або під формою):
```blade
<livewire:shared.profile-completeness type="vacancy" :model-id="$vacancy->id" />
```

Знайди відповідні Blade-файли кабінетів і додай компонент у логічне місце — бокова панель або верх дашборду. Не чіпай інші елементи сторінок.

---

## Тести

Створи `tests/Feature/ProfileCompleteness/ProfileCompletenessTest.php`:

```php
#[Test]
public function empty_candidate_profile_has_low_score(): void
// Кандидат без заповненого профілю → score < 30

#[Test]
public function fully_filled_candidate_profile_has_score_100(): void
// Всі поля заповнені → score = 100, next_step = null

#[Test]
public function next_step_is_highest_weight_missing_field(): void
// Бракує фото (10) та телефон (5) → next_step = фото

#[Test]
public function employer_score_counts_logo_and_description(): void

#[Test]
public function vacancy_score_requires_min_description_length(): void
// description < 200 символів → поле не зараховується

#[Test]
public function vacancy_score_includes_skill_tags(): void
```

---

## Чого НЕ робити
- Не створювати окремих сервісів для кожного типу — один `ProfileCompletenessService` з трьома методами
- Не зберігати score в БД — рахувати динамічно при кожному рендері
- Не чіпати існуючі міграції та моделі — тільки читати дані
- Не використовувати стандартні Livewire компоненти — тільки Volt
- Не розміщувати компонент на публічних сторінках — тільки в авторизованих кабінетах

# Завдання: AI-рекомендації вакансій та кандидатів

## Контекст проєкту
Платформа My Job (myjob.co.ua). Laravel 13, Livewire 3 (Volt components), Filament, PostgreSQL, Redis.
Ролі: `UserRole::Candidate` та `UserRole::Employer` (Enum).
PHPUnit 12 — атрибути `#[Test]`.
`$this->actingAs()` викликається ДО `Volt::test()`.

---

## Що реалізувати

### 1. Migrations

**`skills`** — нормалізований словник навичок:
```
id
name      — string, unique  ('Laravel', 'Vue.js', 'Docker')
slug      — string, unique
category  — string, nullable ('backend', 'frontend', 'design', 'management')
timestamps
```

**`candidate_skills`** — навички кандидата:
```
user_id    — FK → users, cascadeOnDelete
skill_id   — FK → skills, cascadeOnDelete
level      — tinyInteger, default 1  (1=beginner, 5=expert)
PRIMARY KEY (user_id, skill_id)
```

**`vacancy_skills`** — навички вакансії:
```
vacancy_id   — FK → vacancies, cascadeOnDelete
skill_id     — FK → skills, cascadeOnDelete
is_required  — boolean, default true
PRIMARY KEY (vacancy_id, skill_id)
```

**`vacancy_recommendations`** — кеш рекомендацій:
```
id
user_id     — FK → users, cascadeOnDelete
vacancy_id  — FK → vacancies, cascadeOnDelete
score       — unsignedTinyInteger  (0–100)
calculated_at — timestamp
UNIQUE (user_id, vacancy_id)
INDEX (user_id, score)
```

### 2. Моделі та Relationships

**`Skill`** model:
- `hasMany` через `candidate_skills` → Users
- `hasMany` через `vacancy_skills` → Vacancies

**`User`** model — додати:
- `belongsToMany(Skill::class)->withPivot('level')` → `candidateSkills()`
- `hasMany(VacancyRecommendation::class)` → `recommendations()`

**`Vacancy`** model — додати:
- `belongsToMany(Skill::class)->withPivot('is_required')` → `skills()`

### 3. Service: `RecommendationService`

Файл: `app/Services/RecommendationService.php`

Методи:

**`calculateScore(User $candidate, Vacancy $vacancy): int`**

Rule-based алгоритм (0–100 балів):
```
Базові критерії (по 10 балів кожен, max 30):
  - збіг міста або вакансія remote
  - зарплатна вилка перетинається з очікуваннями кандидата
  - збіг категорії (наприклад, 'backend')

Навички (max 70 балів):
  - для кожної required skill вакансії:
      є у кандидата → +weight
      немає → 0
  - weight = 70 / кількість required skills
  - бажані (is_required=false) навички додають до 10% бонусу
```

**`recalculateForUser(User $candidate): void`**

- Отримати всі активні вакансії
- Для кожної: `calculateScore()`
- Зберегти у `vacancy_recommendations` (upsert)
- Зберігати тільки score >= 50

**`recalculateForVacancy(Vacancy $vacancy): void`**

- Отримати всіх кандидатів з заповненим профілем
- Для кожного: `calculateScore()`
- Зберегти у `vacancy_recommendations`

### 4. Job: `RecalculateRecommendationsJob`

Файл: `app/Jobs/RecalculateRecommendationsJob.php`

- Приймає `User|Vacancy $target`
- Викликає відповідний метод `RecommendationService`
- Queue: `recommendations`

### 5. Observers — тригери перерахунку

**`CandidateProfileObserver`** (на модель `User` або `CandidateProfile`):
- При `saved` → диспатчить `RecalculateRecommendationsJob` для цього кандидата

**`VacancyObserver`**:
- При `saved` → диспатчить `RecalculateRecommendationsJob` для цієї вакансії

### 6. Volt-компонент: `candidate.recommended-vacancies`

Файл: `resources/views/livewire/candidate/recommended-vacancies.blade.php`

Відображає:
- Заголовок «🎯 Рекомендовано для вас»
- Список вакансій відсортованих за score DESC
- Для кожної вакансії:
  - Назва + компанія + місто + зарплата
  - Score-бар (прогрес-бар, колір залежить від score):
    - 90–100 → зелений + «Відмінний збіг»
    - 70–89  → жовтий + «Хороший збіг»
    - < 70   → сірий
  - Список навичок: є у кандидата → зелена галочка ✓, немає → червоний ✗
  - Якщо є відсутні required skills → блок «⚠ Бракує: Docker, Redis»
  - Кнопка «Відгукнутись»
- Якщо рекомендацій немає → «Заповніть профіль, щоб отримати рекомендації»

### 7. Volt-компонент: `employer.recommended-candidates`

Файл: `resources/views/livewire/employer/recommended-candidates.blade.php`

Props: `$vacancyId`

Відображає:
- Заголовок «🤖 Рекомендовані кандидати»
- Список кандидатів за score DESC
- Для кожного: ім'я, score-бар, навички з галочками
- Кнопка «Запросити» → змінює статус відгуку або створює новий

### 8. Filament Resource: `SkillResource`

- CRUD для словника навичок (адмін-панель)
- Поля: name, slug (auto-generated), category
- Таблиця з фільтром по category

---

## Тести

Створи `tests/Feature/Recommendation/RecommendationServiceTest.php`:

```php
#[Test]
public function score_is_100_when_all_skills_match(): void

#[Test]
public function score_is_0_when_no_skills_match(): void

#[Test]
public function score_considers_city_match(): void

#[Test]
public function score_considers_salary_range(): void

#[Test]
public function recommendations_are_saved_to_database(): void

#[Test]
public function low_score_vacancies_are_excluded(): void

#[Test]
public function recalculation_is_triggered_on_profile_update(): void
```

---

## Чого НЕ робити
- Не використовувати ML-моделі, embeddings, OpenAI API — тільки rule-based алгоритм
- Не рахувати score синхронно при кожному запиті — тільки через Job + кеш
- Не чіпати існуючі міграції
- Не використовувати рядкові ролі — тільки UserRole Enum
- Не використовувати стандартні Livewire компоненти — тільки Volt

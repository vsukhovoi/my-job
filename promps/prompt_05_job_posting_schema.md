# Завдання: Schema.org JobPosting — структурована розмітка вакансій

## Контекст проєкту
Платформа My Job (myjob.co.ua). Laravel 13, Livewire 3 (Volt), Blade-шаблони, PostgreSQL.
Існуюча модель `Vacancy` має поля: title, description, created_at, expires_at, salary_from, salary_to, salary_currency, employment_type, is_remote, city (або location).
PHPUnit 12 — атрибути `#[Test]`.

Перед початком: перевір реальну структуру моделі `Vacancy` і таблиці `vacancies` — назви полів можуть відрізнятись від наведених вище. Адаптуй реалізацію під фактичні поля.

---

## Що реалізувати

### 1. View Component: `JobPostingSchema`

Файл: `app/View/Components/JobPostingSchema.php`

Приймає: `Vacancy $vacancy`

Метод `schema(): array` формує масив згідно зі специфікацією Schema.org JobPosting:

**Обов'язкові поля:**
- `@context` → `https://schema.org/`
- `@type` → `JobPosting`
- `title` → `$vacancy->title`
- `description` → `strip_tags($vacancy->description)` (без HTML)
- `datePosted` → `$vacancy->created_at->toDateString()`
- `validThrough` → `$vacancy->expires_at?->toIso8601String()` (якщо є)

**Організація:**
```json
"hiringOrganization": {
  "@type": "Organization",
  "name": "Назва компанії",
  "sameAs": "https://myjob.co.ua/employers/{slug}"
}
```

**Локація (офісна вакансія):**
```json
"jobLocation": {
  "@type": "Place",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Дніпро",
    "addressCountry": "UA"
  }
}
```

**Remote-вакансія** (якщо `is_remote = true`):
```json
"jobLocationType": "TELECOMMUTE",
"applicantLocationRequirements": {
  "@type": "Country",
  "name": "Ukraine"
}
```
Для гібридних вакансій — додавати обидва блоки (jobLocation + jobLocationType).

**Зарплата** (якщо `salary_from` або `salary_to` заповнені):
```json
"baseSalary": {
  "@type": "MonetaryAmount",
  "currency": "UAH",
  "value": {
    "@type": "QuantitativeValue",
    "minValue": 50000,
    "maxValue": 80000,
    "unitText": "MONTH"
  }
}
```
Якщо тільки одне значення — використовувати `value` замість `minValue`/`maxValue`.

**Тип зайнятості** — маппінг з внутрішніх значень на Schema.org:
```php
private function mapEmploymentType(): string
{
    return match($this->vacancy->employment_type) {
        'full_time'  => 'FULL_TIME',
        'part_time'  => 'PART_TIME',
        'contract'   => 'CONTRACTOR',
        'freelance'  => 'TEMPORARY',
        'internship' => 'INTERN',
        default      => 'OTHER',
    };
}
```
Адаптуй значення під фактичний Enum або рядки у моделі Vacancy.

### 2. Blade-шаблон компонента

Файл: `resources/views/components/job-posting-schema.blade.php`

```blade
<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
</script>
```

### 3. Підключення у layout

У головному Blade-layout додати `@stack('schema')` перед закриваючим `</head>`.

На сторінці публічної вакансії (`vacancies.show` або аналог) додати:

```blade
@push('schema')
    <x-job-posting-schema :vacancy="$vacancy" />
@endpush
```

Підключати тільки на публічних сторінках вакансій — не в адмін-панелі, не в кабінеті роботодавця.

### 4. Sitemap-підтримка (опційно, якщо вже є sitemap)

Якщо в проєкті є sitemap — переконайся що публічні URL вакансій включені. Якщо sitemap відсутній — не створювати, це окреме завдання.

---

## Тести

Створи `tests/Feature/Seo/JobPostingSchemaTest.php`:

```php
#[Test]
public function vacancy_page_contains_job_posting_schema(): void
// GET /vacancies/{slug} → response містить "application/ld+json" і "@type":"JobPosting"

#[Test]
public function schema_includes_salary_when_present(): void
// Вакансія з salary_from/salary_to → schema містить baseSalary

#[Test]
public function schema_omits_salary_when_absent(): void
// Вакансія без зарплати → schema не містить baseSalary

#[Test]
public function remote_vacancy_includes_telecommute_field(): void
// is_remote = true → schema містить "jobLocationType": "TELECOMMUTE"

#[Test]
public function schema_strips_html_from_description(): void
// description з HTML-тегами → schema містить чистий текст
```

---

## Чого НЕ робити
- Не додавати розмітку на сторінки кабінету, адмін-панелі або списку вакансій — тільки на сторінку окремої вакансії
- Не виводити розмітку для вакансій зі статусом draft або expired
- Не створювати нових міграцій — використовувати існуючі поля
- Не чіпати Filament-ресурси

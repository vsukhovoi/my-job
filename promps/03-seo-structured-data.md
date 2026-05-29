# Промпт 3 — Розширення структурованих даних

## Мета

Додати дві відсутні Schema.org-розмітки: (1) `BreadcrumbList` JSON-LD на сторінці вакансії (дає rich snippet хлібних крихт), (2) `WebSite` + `SearchAction` на головній (можливість Sitelinks Search Box у Google).

---

## Контекст і конвенції (обовʼязково дотримуватись)

- Стек: Laravel 13.4 + PHP 8.3, Livewire 3 Volt (**тільки Class API**), Blade.
- **Розширення, а не переписування:** наявний `JobPostingSchema` та HTML-breadcrumb не чіпати — лише додати окремі JSON-LD блоки. Перед змінами — Крок 1.
- **Патерн Livewire:** для View Component зі структурованими даними використовувати той самий підхід, що в наявному `JobPostingSchema` (`readonly array $ldJson` у конструкторі — обхід для Livewire).
- **Тести (PHPUnit 12):** тільки `#[Test]`; `actingAs()` окремо **до** `Volt::test(...)`.
- **Гейти:** між кроками — СТОП із підтвердженням.
- **Без регресій.** Нові тести — 7 шт.

---

## Крок 1 — Розвідка (без змін)

1. `app/View/Components/JobPostingSchema.php` — як саме реалізовано (конструктор, `readonly array $ldJson`, рендер `<script type="application/ld+json">`). Це еталонний патерн для нових компонентів.
2. `resources/views/livewire/pages/jobs/show.blade.php` — наявний HTML-breadcrumb (за памʼяттю ~рядок 226): які саме рівні (Вакансії → Категорія → Вакансія), звідки беруться назви та URL.
3. `resources/views/layouts/app.blade.php` — `<head>`: куди вставити `WebSite`-schema **лише на головній** (перевір, чи є умова визначення головної сторінки / окрема секція head).
4. Як визначається базовий URL пошуку для `SearchAction` (маршрут списку вакансій + параметр запиту).

**СТОП-гейт 1.** Виведи: який патерн компонента використаєш, які рівні breadcrumb, як обмежиш `WebSite`-schema головною. Підтвердження перед Кроком 2.

---

## Крок 2 — `BreadcrumbListSchema` View Component

Створити View Component за патерном `JobPostingSchema`:
- приймає колекцію елементів `[name, url]` у порядку ієрархії;
- формує JSON-LD `BreadcrumbList` із `itemListElement`, де кожен `ListItem` має `position` (з 1), `name`, `item` (абсолютний URL);
- рендерить `<script type="application/ld+json">`.

Підключити на сторінці вакансії поряд із наявним HTML-breadcrumb (HTML не видаляти — лишити для користувача). Рівні мають точно відповідати видимому breadcrumb.

**СТОП-гейт 2.** Підтвердження перед Кроком 3.

---

## Крок 3 — `WebSiteSchema` (головна)

Створити View Component `WebSiteSchema` за тим самим патерном:
- `@type: WebSite`, `name`, `url` (базовий);
- вкладений `potentialAction` типу `SearchAction` із `target` (URL-шаблон пошуку з `{search_term_string}`) та `query-input: "required name=search_term_string"`.

Підключити **тільки на головній** (не на всіх сторінках).

**СТОП-гейт 3.** Перед тестами підтвердити фінальну структуру обох JSON-LD.

---

## Крок 4 — Тести (7 шт., PHPUnit 12, `#[Test]`)

1. Сторінка вакансії містить JSON-LD з `@type` = `BreadcrumbList`.
2. `BreadcrumbList` має коректну кількість `itemListElement` із послідовними `position` (1..N).
3. Кожен `ListItem` у breadcrumb має абсолютний `item`-URL і непорожній `name`.
4. Рівні breadcrumb у schema відповідають HTML-breadcrumb (та сама вакансія/категорія).
5. Головна містить JSON-LD з `@type` = `WebSite` і вкладеним `SearchAction`.
6. `SearchAction.target` містить плейсхолдер `{search_term_string}`, а `query-input` має коректний формат.
7. `WebSite`-schema **відсутня** на сторінці вакансії (рендериться лише на головній).

---

## Критерії завершення

- 7 нових тестів зелені, **0 регресій**.
- Обидва JSON-LD валідні структурно; наявний `JobPostingSchema` не змінено.
- HTML-breadcrumb для користувача збережено.

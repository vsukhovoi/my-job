# Промпт 4 — SEO статичних сторінок + sitemap

## Мета

Привести статичні сторінки (`/about`, `/contacts`) до повного SEO-стандарту (унікальні `title`, canonical, повний OG-набір через секції layout) та виправити sitemap (active-scope, виключення анонімних вакансій, додавання статичних сторінок).

---

## Контекст і конвенції (обовʼязково дотримуватись)

- Стек: Laravel 13.4 + PHP 8.3, Blade, MySQL `my_job`.
- **Enum-и:** для статусів вакансій використовувати наявний scope/Enum (`VacancyStatus::Active` / scope `active()`), не `where('is_active', true)`.
- **Анонімність:** виключення анонімних вакансій узгодити з наявною логікою (`VacancyPublicationType`, поле/умова анонімності, як у `vacancies:refresh-anonymous`).
- **Розширення, а не переписування:** наявний контент сторінок не переписувати — лише додати/уніфікувати SEO-секції. Перед змінами — Крок 1.
- **Копірайтинг:** українською; без заборонених рекрутингово-посередницьких формулювань.
- **Тести (PHPUnit 12):** тільки `#[Test]`.
- **Гейти:** між кроками — СТОП із підтвердженням.
- **Без регресій.** Нові тести — 7 шт.

---

## Крок 1 — Розвідка (без змін)

1. `resources/views/layouts/app.blade.php` — які SEO-секції підтримує layout (`@yield('seo_title')`, `@yield('seo_canonical')`, `@yield('seo_description')`, OG-секції). Зафіксуй точні імена секцій/`@yield`.
2. `resources/views/pages/about.blade.php` та `contacts.blade.php` — як зараз задаються `title`/`description`/OG (за памʼяттю — частина через прямі `<meta>` у blade, що може дублювати fallback layout).
3. `routes/web.php` (маршрут sitemap, ~рядки 59–61) + `resources/views/sitemap.blade.php` — як формується список URL, який фільтр для вакансій (`is_active`?), чи є статичні сторінки, чи виключені анонімні.
4. Наявний scope `active()` / `VacancyStatus` на моделі `Vacancy`; умова анонімності.

**СТОП-гейт 1.** Виведи: точні імена SEO-секцій layout, поточний фільтр sitemap, як виключатимеш анонімні. Підтвердження перед Кроком 2.

---

## Крок 2 — Статичні сторінки `/about` та `/contacts`

Перевести SEO кожної сторінки на секції layout (а не прямі `<meta>`, щоб уникнути подвійних тегів у `<head>`):
- `@section('seo_title', '…')` — унікальний title для кожної сторінки;
- `@section('seo_canonical', url('/about'))` / `url('/contacts')`;
- `@section('seo_description', '…')` — унікальний опис без заборонених фраз;
- повний OG-набір: `og:title`, `og:description`, `og:type`, `og:url`, `og:image`, `twitter:card` (через відповідні секції layout або уніфікований механізм).

Видалити прямі `<meta description>`, якщо вони дублюють fallback layout.

**СТОП-гейт 2.** Виведи фінальні значення title/description/canonical для обох сторінок. Підтвердження перед Кроком 3.

---

## Крок 3 — Sitemap

У джерелі sitemap:
- замінити `where('is_active', true)` на scope `Vacancy::query()->active()` (через Enum/scope);
- **виключити анонімні вакансії** згідно з наявною логікою анонімності;
- додати статичні сторінки `/about`, `/contacts` (та `/`, якщо відсутня);
- `/offer` лишити **поза** sitemap (вона `noindex`).

(Сторінки компаній і `<priority>` — поза цією задачею, це окрема фіча пост-запуск.)

**СТОП-гейт 3.** Перед тестами підтвердити підсумковий перелік типів URL у sitemap.

---

## Крок 4 — Тести (7 шт., PHPUnit 12, `#[Test]`)

1. `/about` повертає унікальний `<title>` (не дефолтний `config('app.name')`).
2. `/about` містить self-canonical на `url('/about')`.
3. `/about` містить повний OG-набір (`og:image`, `og:url`, `og:type`, `twitter:card`).
4. `/contacts` містить self-canonical на `url('/contacts')` і унікальний title.
5. Sitemap включає активну вакансію і **не** включає неактивну.
6. Sitemap **не** включає анонімну вакансію.
7. Sitemap включає `/about` та `/contacts` і **не** включає `/offer`.

---

## Критерії завершення

- 7 нових тестів зелені, **0 регресій**.
- У `<head>` статичних сторінок немає подвійних `description`/canonical.
- Sitemap не містить неактивних, анонімних вакансій чи `noindex`-сторінок.

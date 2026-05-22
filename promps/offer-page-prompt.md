# Завдання: Створення сторінки «Договір публічної оферти» для My Job

## Стек проєкту (довідково — не змінювати)

**Backend:** Laravel 13.4.0 + PHP 8.3 · MySQL (БД `my_job`) · Redis · Nutgram (Telegram bot) · LiqPay / WayForPay / MonoPay / IBAN (Stripe — заглушка на майбутнє)
**Frontend:** Livewire Volt (Class API) · Blade · Tailwind CSS (Mobile First)
**Admin:** FilamentPHP v4 (`/admin`)
**Infrastructure:** OVHcloud VPS Ubuntu 22.04 · Docker + Docker Compose · Nginx · Cloudflare
**Email:** Resend (smtp.resend.com)
**CI/CD:** GitHub Actions → автодеплой на `main`

---

## Контекст завдання

Створити статичну сторінку `/offer` з повним текстом Договору публічної оферти для платформи My Job (myjob.co.ua). Сторінка використовує **існуючі хедер та футер** без змін. Посилання на сторінку — лише у футері. Сторінка є **суто статичним Blade-шаблоном без Livewire Volt**.

---

## 1. Роутинг

Додай маршрут у `routes/web.php`:

```php
Route::get('/offer', function () {
    return view('pages.offer');
})->name('offer');
```

---

## 2. Посилання у футері

Знайди файл футера (за аналогією з тим, як додавалось посилання `/about`) і додай у відповідну секцію:

```blade
<a href="{{ route('offer') }}"
   class="text-sm text-gray-400 hover:text-gray-600 transition">
    Публічна оферта
</a>
```

---

## 3. Файл View

Створи файл: `resources/views/pages/offer.blade.php`

**Перед написанням** — подивись на `resources/views/pages/about.blade.php` і використай точно такий самий спосіб підключення лейауту.

---

## 4. Структура та повний контент сторінки

Сторінка — юридичний документ, тому дизайн: чистий, читабельний, без декоративних елементів. Суцільний текст з чіткою ієрархією заголовків.

### Загальна обгортка

```html
<div class="max-w-3xl mx-auto px-6 py-16">

    {{-- ШАПКА ДОКУМЕНТА --}}
    {{-- РОЗДІЛИ 1–8 --}}
    {{-- РЕКВІЗИТИ --}}

</div>
```

---

### 4.1 Шапка документа

```html
<div class="mb-12 text-center border-b border-gray-200 pb-10">
    <h1 class="text-2xl font-extrabold text-gray-900 mb-3 leading-snug">
        ДОГОВІР ПУБЛІЧНОЇ ОФЕРТИ
    </h1>
    <p class="text-sm text-gray-500">
        про надання послуг з оброблення даних та розміщення інформації
    </p>
    <div class="mt-4 flex justify-center gap-8 text-xs text-gray-400">
        <span>Дата публікації: 13 травня 2026 року</span>
        <span>Місце публікації: myjob.co.ua</span>
    </div>
</div>

<div class="mb-10 text-sm text-gray-600 leading-relaxed bg-gray-50 rounded-xl p-6 border border-gray-100">
    <p>
        Товариство з обмеженою відповідальністю <strong>«ФЛАГМАН СВ»</strong>
        (код ЄДРПОУ 37490783, що є платником єдиного податку за ставкою 5% без ПДВ),
        в особі засновника, який діє на підставі Рішення №1 від 30.06.2025 р. (надалі — <strong>Виконавець</strong>),
        з однієї сторони, та будь-яка юридична особа, фізична особа-підприємець або дієздатна
        фізична особа, яка акцептувала цей Договір (надалі — <strong>Клієнт</strong>),
        з іншої сторони (разом надалі — <strong>Сторони</strong>), уклали цей Договір про таке:
    </p>
</div>
```

---

### 4.2 Розділ 1 — Загальні положення та акцепт

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        1. Загальні положення та акцепт
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">1.1.</span>
            Цей Договір є публічною офертою (пропозицією) відповідно до ст. 633 та ст. 641
            Цивільного кодексу України.
        </p>
        <p>
            <span class="font-semibold text-gray-800">1.2.</span>
            Повним і безумовним прийняттям (акцептом) умов цієї Оферти є здійснення Клієнтом
            будь-якої з таких дій: реєстрація Особистого кабінету на Вебсайті Виконавця
            myjob.co.ua, внесення передоплати за Послуги (оновлення тарифного плану),
            фактична публікація вакансії або використання будь-яких інформаційно-пошукових
            сервісів Сайту.
        </p>
        <p>
            <span class="font-semibold text-gray-800">1.3.</span>
            З моменту акцепту цей Договір набуває чинності договору приєднання (ст. 634 ЦК
            України) та має юридичну силу договору, підписаного Сторонами двосторонньо.
        </p>
    </div>
</section>
```

---

### 4.3 Розділ 2 — Предмет договору

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        2. Предмет договору
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">2.1.</span>
            Виконавець зобов'язується надати Клієнту послуги з оброблення даних, розміщення
            інформації на вебвузлах (Вебсайті), надання доступу до програмного інтерфейсу та
            пошукового механізму бази даних (надалі — <strong>Послуги</strong>), а Клієнт
            зобов'язується прийняти та оплатити Послуги на умовах цього Договору.
        </p>
        <p>
            <span class="font-semibold text-gray-800">2.2.</span>
            <span class="font-semibold text-gray-800">Юридичний статус послуг:</span>
            Сторони чітко усвідомлюють, що Виконавець надає виключно технічні та інформаційні
            ІТ-послуги (КВЕД 63.11). Виконавець не є агентством з працевлаштування (КВЕД 78.10),
            не здійснює професійний підбір, оцінку, тестування, працевлаштування чи гарантування
            найму персоналу.
        </p>
    </div>
</section>
```

---

### 4.4 Розділ 3 — Порядок надання послуг та монетизація

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        3. Порядок надання послуг та монетизація
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">3.1.</span>
            Послуги надаються шляхом надання Клієнту технічної можливості публікувати
            інформаційні оголошення (вакансії) у відповідних категоріях Сайту та/або отримувати
            допуск до пошукових фільтрів бази даних резюме користувачів на Сайті.
        </p>
        <p>
            <span class="font-semibold text-gray-800">3.2.</span>
            Обсяг, вартість та технічні переваги Послуг (ліміти на публікацію вакансій,
            терміни їх відображення) визначаються Тарифами, які розміщені на Вебсайті та є
            невід'ємною частиною цього Договору.
        </p>
        <p>
            <span class="font-semibold text-gray-800">3.3.</span>
            Виконавець надає Послуги на умовах 100% передоплати.
        </p>
    </div>
</section>
```

---

### 4.5 Розділ 4 — Вартість послуг та порядок розрахунків

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        4. Вартість послуг та порядок розрахунків
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">4.1.</span>
            Розрахунки за цим Договором здійснюються у національній валюті України — гривні,
            виключно у безготівковій формі.
        </p>
        <p>
            <span class="font-semibold text-gray-800">4.2.</span>
            Оплата здійснюється Клієнтом шляхом перерахування грошових коштів на поточний
            рахунок (IBAN) Виконавця або через інтегровані на Вебсайті сервіси
            інтернет-еквайрингу (платіжні системи за допомогою карт Visa/Mastercard).
        </p>
        <p>
            <span class="font-semibold text-gray-800">4.3.</span>
            Послуги надаються без ПДВ (у зв'язку із застосуванням Виконавцем спрощеної системи
            оподаткування).
        </p>
    </div>
</section>
```

---

### 4.6 Розділ 5 — Порядок приймання-передачі послуг

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        5. Порядок приймання-передачі послуг
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">5.1.</span>
            Послуги вважаються наданими Виконавцем належним чином, в повному обсязі та
            прийнятими Клієнтом у момент активації відповідного тарифного пакету, успішної
            публікації вакансії на Сайті або відкриття доступу до бази даних.
        </p>
        <p>
            <span class="font-semibold text-gray-800">5.2.</span>
            Сторони погодили, що надання Послуг за цим Договором не потребує підписання
            двосторонніх паперових Актів приймання-передачі наданих послуг.
        </p>
        <p>
            <span class="font-semibold text-gray-800">5.3.</span>
            Якщо Клієнт протягом 3 (трьох) календарних днів з моменту надання Послуги не
            заявив обґрунтовану письмову претензію щодо її якості, Послуга вважається
            виконаною бездоганно і прийнятою Клієнтом.
        </p>
    </div>
</section>
```

---

### 4.7 Розділ 6 — Обмеження відповідальності

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        6. Обмеження відповідальності
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">6.1.</span>
            Виконавець не несе відповідальності за зміст, точність та законність інформації,
            яка самостійно розміщується третіми особами (користувачами, пошукачами) у формі
            резюме чи відгуків.
        </p>
        <p>
            <span class="font-semibold text-gray-800">6.2.</span>
            Виконавець не відповідає за результати співбесід, укладення чи неукладення
            трудових та господарських відносин між Клієнтом та кандидатами.
        </p>
        <p>
            <span class="font-semibold text-gray-800">6.3.</span>
            Виконавець залишає за собою право видалити будь-яку інформацію (вакансію) Клієнта
            без повернення коштів, якщо вона містить ознаки дискримінації (за статтю, віком,
            расою тощо), заклики до порушення чинного законодавства України або містить
            завідомо неправдиві дані.
        </p>
    </div>
</section>
```

---

### 4.8 Розділ 7 — Персональні дані

```html
<section class="mb-10">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        7. Персональні дані
    </h2>
    <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
        <p>
            <span class="font-semibold text-gray-800">7.1.</span>
            Клієнт дає згоду на обробку своїх персональних та корпоративних даних Виконавцем
            відповідно до Закону України «Про захист персональних даних» з метою виконання
            умов цього Договору, верифікації профілю компанії та проведення розрахунків.
        </p>
    </div>
</section>
```

---

### 4.9 Розділ 8 — Реквізити виконавця

```html
<section class="mb-2">
    <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
        8. Реквізити виконавця
    </h2>
    <div class="bg-gray-50 rounded-xl border border-gray-100 p-6">
        <table class="w-full text-sm text-gray-600 leading-relaxed">
            <tbody class="divide-y divide-gray-100">
                <tr class="py-2">
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap w-48">Повна назва</td>
                    <td class="py-2.5">Товариство з обмеженою відповідальністю «ФЛАГМАН СВ»</td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Код ЄДРПОУ</td>
                    <td class="py-2.5">37490783</td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Юридична адреса</td>
                    <td class="py-2.5">52410, Дніпропетровська обл., Дніпровський район, село Сурсько-Михайлівка, вул. Виноградна, буд. 20</td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">IBAN</td>
                    <td class="py-2.5 font-mono">UA423052990000026009050581926</td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Банк</td>
                    <td class="py-2.5">АТ КБ «Приватбанк»</td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Оподаткування</td>
                    <td class="py-2.5">Єдиний податок 3 група, 5% без ПДВ</td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Email</td>
                    <td class="py-2.5">
                        {{-- Підстав актуальний email для зв'язку --}}
                        <a href="mailto:info@myjob.co.ua" class="text-blue-600 hover:underline">info@myjob.co.ua</a>
                    </td>
                </tr>
                <tr>
                    <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Підписант</td>
                    <td class="py-2.5">Засновник</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
```

---

### 4.10 Нижній колонтитул документа

```html
<div class="mt-10 pt-8 border-t border-gray-200 text-xs text-gray-400 text-center leading-relaxed">
    <p>
        Цей Договір є публічною офертою відповідно до ст. 633, 634, 641 Цивільного кодексу України.<br>
        Актуальна версія завжди доступна за адресою:
        <a href="{{ url('/offer') }}" class="text-blue-500 hover:underline">{{ url('/offer') }}</a>
    </p>
</div>
```

---

## 5. SEO мета-теги

Якщо лейаут підтримує `@stack('meta')` — додай у `offer.blade.php`:

```blade
@push('meta')
<meta name="description" content="Договір публічної оферти про надання послуг з оброблення даних та розміщення інформації — My Job (myjob.co.ua).">
<meta name="robots" content="noindex, follow">
<meta property="og:title" content="Публічна оферта — My Job">
<meta property="og:url" content="{{ url('/offer') }}">
@endpush
```

> `noindex` — стандартна практика для юридичних сторінок: вони не повинні займати місце у пошуковій видачі.

---

## 6. Чеклист після реалізації

- [ ] `php artisan route:list | grep offer` — маршрут зареєстровано
- [ ] Сторінка відкривається за `/offer` без помилок (500/404 відсутні)
- [ ] Хедер та футер відображаються коректно (без подвоєння)
- [ ] Посилання «Публічна оферта» у футері веде на `/offer`
- [ ] Email у реквізитах (розділ 8) — підставлений актуальний
- [ ] Таблиця реквізитів читабельна на мобільних (перевір на 375px)
- [ ] `<title>` сторінки — «Публічна оферта — My Job»
- [ ] `storage/logs/laravel.log` — без нових помилок після деплою

---

## Важливі примітки

- Сторінка **повністю статична** — жодних міграцій, моделей, Volt-компонентів, контролерів.
- Текст договору відтворюється **дослівно** з офіційного документа — не перефразовувати, не скорочувати.
- **Email у розділі 8** — у PDF стоїть заглушка `[Вкажіть email для зв'язку]`. Підстав реальний email або залиш Blade-коментар `{{-- TODO: підставити email --}}` і запитай у власника.
- Не чіпати `/admin`, Filament-ресурси та Volt-компоненти.
- Лейаут — той самий, що у `resources/views/pages/about.blade.php`.

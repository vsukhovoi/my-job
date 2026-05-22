# Claude Code Prompt: Billing — «Додаткові послуги» (Варіант А)

## Контекст

Проект **My Job** — Laravel 13, Livewire 3 (Volt-компоненти), Tailwind CSS, PHPUnit 12.

Поточний стан `CheckoutService`:
- Існує єдиний метод `createPlanSubscriptionCheckout(SubscriptionPlan $plan, User $employer)`
- Сторінка `dashboard/employer/billing/checkout` прив'язана до `SubscriptionPlan $plan` через route model binding
- Платіжні шлюзи: **LiqPay** і **WayForPay** працюють у тестовому режимі. MonoPay — не підключено, не додавати.

**Це завдання складається з двох частин:**
1. Бекенд — розширення `CheckoutService` + роутингу + checkout-сторінки
2. Фронтенд — layout сторінки `billing` + блок «Додаткові послуги»

Виконувати **строго в зазначеному порядку**. Після кожного кроку — підтвердження.

---

## ЧАСТИНА 1. Бекенд

### Крок 1 — Розвідка

Перед будь-якими змінами виконай і покажи результат:

```bash
# Структура CheckoutService
cat app/Services/CheckoutService.php

# Поточний роут checkout
php artisan route:list | grep checkout

# Volt-компонент або контролер checkout
find resources/views/livewire/employer -name "*checkout*" -o -name "*billing*" | sort
find app/Http/Controllers -name "*Checkout*" -o -name "*Billing*" | sort

# Enum або константи якщо є
find app/Enums -type f | sort
```

Покажи результат. **Зупинись і чекай підтвердження.**

---

### Крок 2 — Enum `AddonType`

**Підтвердь перед виконанням.**

Створити: `app/Enums/AddonType.php`

```php
enum AddonType: string
{
    case Hot      = 'hot';
    case Top      = 'top';
    case CvAccess = 'cv_access';

    public function label(): string
    {
        return match($this) {
            self::Hot      => 'HOT — підняття вакансії вгору',
            self::Top      => 'TOP — закріплення у топових позиціях',
            self::CvAccess => 'Доступ до бази CV',
        };
    }

    public function price(): int
    {
        return match($this) {
            self::Hot      => 199,
            self::Top      => 299,
            self::CvAccess => 990,
        };
    }

    public function durationDays(): int
    {
        return match($this) {
            self::Hot      => 7,
            self::Top      => 7,
            self::CvAccess => 30,
        };
    }
}
```

**Зупинись і чекай підтвердження.**

---

### Крок 3 — Розширення `CheckoutService`

**Підтвердь перед виконанням.**

Додати до існуючого `CheckoutService` новий метод. **Існуючий метод `createPlanSubscriptionCheckout` не чіпати.**

```php
public function createAddonCheckout(AddonType $addon, User $employer): string
{
    // Повертає URL для редіректу на сторінку оплати шлюзу.
    // orderId формат: addon_{$addon->value}_{$employer->id}_{timestamp}
    // Логіка побудови запиту до шлюзу — аналогічна createPlanSubscriptionCheckout.
    // Якщо є спільний private-метод для побудови запиту — використати його, не дублювати.
}
```

**Зупинись і чекай підтвердження.**

---

### Крок 4 — Роут для addon checkout

**Підтвердь перед виконанням.**

Знайти існуючий роут `dashboard/employer/billing/checkout` у `routes/web.php`.
Поруч додати новий роут для addon:

```php
Route::get('dashboard/employer/billing/checkout/addon/{addon}', ...)
    ->name('employer.billing.checkout.addon')
    ->middleware(['auth', 'verified', 'role:employer']);
```

`{addon}` — значення `AddonType` enum (`hot` / `top` / `cv_access`).
Використати implicit enum binding Laravel 10+. Невалідний тип → 404 автоматично.

**Зупинись і чекай підтвердження.**

---

### Крок 5 — Checkout-сторінка для addon

**Підтвердь перед виконанням.**

Знайти існуючий Volt-компонент або Blade-шаблон сторінки `dashboard/employer/billing/checkout`.
**Не переписувати — розширити.**

Додати гілку для `AddonType`:
- Якщо отримано `SubscriptionPlan` → існуюча логіка без змін
- Якщо отримано `AddonType` → показати: назву, опис, ціну (₴), термін дії, дві кнопки шлюзів

**Кнопки шлюзів:**
- `Оплатити через LiqPay` → `CheckoutService::createAddonCheckout($addon, auth()->user())` з gateway=liqpay
- `Оплатити через WayForPay` → те саме з gateway=wayforpay
- MonoPay — **не додавати**

**Зупинись і чекай підтвердження.**

---

## ЧАСТИНА 2. Фронтенд

### Крок 6 — Layout сторінки billing

**Підтвердь перед виконанням.**

Знайти існуючий Volt-компонент сторінки `dashboard/employer/billing`.
Верхня секція зараз містить блок **«Поточний тариф»** на повну ширину.

Змінити layout верхньої секції:

```
┌──────────────────────────┬──────────────────────────┐
│   Поточний тариф         │   Додаткові послуги       │
│   (існуючий блок, 1/2)   │   (новий блок, 1/2)       │
└──────────────────────────┴──────────────────────────┘
```

Tailwind: `grid grid-cols-1 lg:grid-cols-2 gap-6 items-start`

Нижче — решта існуючого контенту (список тарифів, історія) **без змін**.

**Зупинись і чекай підтвердження.**

---

### Крок 7 — Блок «Додаткові послуги»

**Підтвердь перед виконанням.**

Додати правий блок з картками послуг. Картки — вертикальний список, одна під одною.

**Макет картки:**
```
┌────────────────────────────────────────────────────┐
│ 🔥 HOT — підняття вакансії вгору                   │
│    Вакансія з'являється першою у пошуку • 7 днів   │
│                                    199 ₴  [Придбати]│
└────────────────────────────────────────────────────┘
```

**Перелік карток:**

| Іконка | Назва | Опис | Ціна | Термін | Дія кнопки |
|--------|-------|------|------|--------|------------|
| 🔥 | HOT | Вакансія з'являється першою у пошуку | 199 ₴ | 7 днів | `route('employer.billing.checkout.addon', 'hot')` |
| ⭐ | TOP | Закріплення у топових позиціях | 299 ₴ | 7 днів | `route('employer.billing.checkout.addon', 'top')` |
| 🕵️ | Анонімна публікація | Публікація без бренду компанії | — | — | badge `Доступно` + посилання `route('employer.vacancies.create')` |
| 📄 | Доступ до бази CV | Перегляд резюме кандидатів | 990 ₴ | 30 днів | `route('employer.billing.checkout.addon', 'cv_access')` |

Іконки — Heroicons якщо використовується в проекті, інакше emoji.

**Зупинись і чекай підтвердження.**

---

### Крок 8 — PHPUnit тести

**Підтвердь перед виконанням.**

Файл: `tests/Feature/Employer/AddonCheckoutTest.php`

Синтаксис: `#[Test]` атрибут, `RefreshDatabase`, `actingAs()` **до** Volt::test() якщо потрібно.

```
1. addon_checkout_page_renders_for_hot()
   // GET /dashboard/employer/billing/checkout/addon/hot → 200, містить «199» і «HOT»

2. addon_checkout_page_renders_for_top()
   // GET .../top → 200, містить «299» і «TOP»

3. addon_checkout_page_renders_for_cv_access()
   // GET .../cv_access → 200, містить «990»

4. invalid_addon_type_returns_404()
   // GET .../unknown → 404

5. guest_cannot_access_addon_checkout()
   // без auth → redirect to login

6. seeker_cannot_access_addon_checkout()
   // UserRole::Candidate → 403

7. checkout_service_creates_addon_checkout_url()
   // CheckoutService::createAddonCheckout(AddonType::Hot, $employer) → непорожній рядок URL
```

**Зупинись і чекай підтвердження.**

---

## Чеклист

**Бекенд:**
- [ ] Крок 1: розвідка виконана, структура зрозуміла
- [ ] Крок 2: `AddonType` Enum створено з `label()`, `price()`, `durationDays()`
- [ ] Крок 3: `CheckoutService::createAddonCheckout()` додано, існуючий метод не змінено
- [ ] Крок 4: роут `employer.billing.checkout.addon` з implicit enum binding
- [ ] Крок 5: checkout-сторінка розширена для `AddonType` (план-flow без змін)

**Фронтенд:**
- [ ] Крок 6: верхня секція billing — grid 2 колонки на lg+
- [ ] Крок 7: блок «Додаткові послуги» з 4 картками
- [ ] Анонімна публікація — badge `Доступно`, без кнопки «Придбати»
- [ ] Мобільна адаптація: `grid-cols-1` на малих екранах

**Якість:**
- [ ] Крок 8: 7/7 тестів зелені
- [ ] `php artisan test --stop-on-failure` — нуль регресій

# Завдання: Filament-ресурси для управління білінгом

## Контекст проєкту
Платформа My Job (myjob.co.ua). Laravel 13, Filament, PostgreSQL.
PHPUnit 12 — атрибути `#[Test]`.
Існуючі моделі: `SubscriptionPlan`, `EmployerSubscription`.
Існуючі Enum-и: `PlanType` (free/start/business/pro), `PlanFeature`.
Існуючий сервіс: `SubscriptionService`.

---

## Що реалізувати

### 1. SubscriptionPlanResource

Файл: `app/Filament/Resources/SubscriptionPlanResource.php`

**Таблиця (list):**
- Колонки: name, type (PlanType badge), price, currency, is_active (toggle), created_at
- Фільтр по `is_active`
- Сортування по price

**Форма (create/edit):**
- `name` — TextInput, required
- `type` — Select з PlanType Enum, required
- `price` — TextInput numeric, required
- `currency` — Select: UAH / USD, default UAH
- `is_active` — Toggle, default true
- `features` — Repeater або KeyValue для лімітів фіч (PlanFeature Enum як ключі):
  - active_jobs — число
  - hot_per_month — число
  - top_per_month — число
  - analytics — boolean
  - api_access — boolean
  - team_members — число

**Дії:**
- Edit, Delete (з підтвердженням)
- Заборонити Delete якщо є активні підписки на цей план

### 2. EmployerSubscriptionResource

Файл: `app/Filament/Resources/EmployerSubscriptionResource.php`

**Тільки читання — без create/edit/delete.**

**Таблиця (list):**
- Колонки:
  - Роботодавець (employer → user → name + email)
  - План (subscriptionPlan → name + type badge)
  - Статус: active scope — зелений badge, інакше сірий
  - `starts_at` — дата
  - `ends_at` — дата (червоний якщо минула)
  - `created_at`
- Фільтри:
  - По статусу (активна / неактивна)
  - По типу плану (PlanType)
- Пошук по email роботодавця

**Сторінка перегляду (view):**
- Всі поля підписки
- Секція «Використання»:
  - Скільки вакансій опубліковано з ліміту (`canPublishJob()` логіка)
  - Залишок hot / top промо
- Кнопка «Скасувати підписку» → викликає `SubscriptionService::cancel()`

---

## Тести

Створи `tests/Feature/Filament/BillingResourceTest.php`:

```php
#[Test]
public function admin_can_view_subscription_plans_list(): void

#[Test]
public function admin_can_create_subscription_plan(): void

#[Test]
public function admin_can_edit_subscription_plan(): void

#[Test]
public function admin_cannot_delete_plan_with_active_subscriptions(): void

#[Test]
public function admin_can_view_employer_subscriptions_list(): void

#[Test]
public function admin_can_cancel_employer_subscription(): void
```

---

## Чого НЕ робити
- Не чіпати існуючі міграції і моделі — тільки нові Filament-ресурси
- Не додавати create/edit/delete для EmployerSubscriptionResource
- Не змінювати SubscriptionService — тільки викликати існуючі методи
- Не використовувати рядкові ролі — тільки UserRole Enum

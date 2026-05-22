# Завдання: вимкнути Stripe як платіжний провайдер

## Контекст
Проєкт використовує абстракцію `PaymentGateway` (інтерфейс + реєстр провайдерів).
Активні провайдери: MonoPay, WayForPay, LiqPay.
Stripe недоступний для українських компаній — прибираємо його.

## Крок 1. Перевір поточний стан

```bash
grep PAYMENT_GATEWAY .env
composer show stripe/stripe-php 2>/dev/null | grep name
grep -n "StripeGateway\|stripe" app/Payments/PaymentServiceProvider.php
php artisan route:list | grep stripe
```

Покажи мені вивід. Далі не пиши.

---

## Крок 2. `.env` і `.env.example`

У файлі `.env`:
- Знайди рядок `PAYMENT_GATEWAY=...`
- Заміни значення на `mono` (якщо вже `mono` — нічого не роби)
- Знайди і **закоментуй** (додай `#` на початку) всі рядки що починаються з `STRIPE_`:
  ```
  # STRIPE_KEY=...
  # STRIPE_SECRET=...
  # STRIPE_WEBHOOK_SECRET=...
  ```

У файлі `.env.example` — те саме: закоментуй `STRIPE_*` рядки.

**Не видаляй рядки** — тільки коментуй.

Покажи diff обох файлів. Далі не пиши.

---

## Крок 3. `PaymentServiceProvider`

Відкрий `app/Payments/PaymentServiceProvider.php`.

Знайди рядок:
```php
$registry->register($this->app->make(StripeGateway::class));
```

Закоментуй його:
```php
// $registry->register($this->app->make(StripeGateway::class));
// Stripe недоступний для UA-компаній. Клас збережено для можливого повернення.
```

**Не видаляй** import `use App\Payments\Gateways\StripeGateway;` — залиш, просто закоментуй реєстрацію.

Покажи diff файлу. Далі не пиши.

---

## Крок 4. Маршрут `/webhooks/stripe` (якщо існує)

```bash
grep -rn "webhooks/stripe\|webhooks\.stripe\|StripeWebhookController" routes/
```

Якщо знайдено старий маршрут `POST /webhooks/stripe` (з модуля 6, до рефакторингу) — закоментуй його:
```php
// Stripe вимкнено — маршрут деактивовано
// Route::post('/webhooks/stripe', ...);
```

Якщо маршруту немає — нічого не роби, повідом мені.

Покажи результат grep і diff (якщо були зміни). Далі не пиши.

---

## Крок 5. Видалення пакету

```bash
composer remove stripe/stripe-php --no-interaction
```

Якщо команда повертає помилку «not found» — пакет вже не встановлено, повідом мені.

Покажи вивід команди. Далі не пиши.

---

## Крок 6. Тести Stripe (якщо існують)

```bash
find tests -name "*Stripe*" -o -name "*stripe*" 2>/dev/null
```

Якщо знайдено файл (наприклад `tests/Feature/Stripe/WebhookExtendsVacancyTest.php`
або `tests/Feature/Payments/StripeWebhookTest.php`):

Додай `->skip()` до кожного тесту в цьому файлі АБО додай один рядок на початок класу:

```php
// Stripe вимкнено — тести деактивовано
// Для повторного увімкнення: розкоментуй StripeGateway в PaymentServiceProvider
// та встанови: composer require stripe/stripe-php
```

І обгорни клас у:
```php
#[\PHPUnit\Framework\Attributes\Group('stripe')]
```

Якщо файлів не знайдено — повідом мені.

Покажи diff. Далі не пиши.

---

## Крок 7. Перевірка

```bash
# 1. Активний провайдер
php artisan tinker --execute="dump(config('payments.default'));"
# Очікуване: 'mono'

# 2. Stripe більше не в реєстрі
php artisan tinker --execute="
    \$r = app(\App\Http\Controllers\Payments\PaymentGatewayRegistry::class);
    dump(\$r->get('stripe'));
"
# Очікуване: NULL

# 3. MonoPay в реєстрі
php artisan tinker --execute="
    \$r = app(\App\Http\Controllers\Payments\PaymentGatewayRegistry::class);
    dump(\$r->get('mono') instanceof \App\Payments\Gateways\MonoPayGateway);
"
# Очікуване: true

# 4. Запуск тестів (без stripe-групи)
php artisan test --exclude-group=stripe
# Очікуване: всі тести зелені
```

Покажи вивід усіх чотирьох команд.

---

## Критичні обмеження

- ❌ Не видаляй `StripeGateway.php` — тільки деактивуй реєстрацію
- ❌ Не видаляй `STRIPE_*` з `.env` — тільки коментуй
- ❌ Не видаляй тести — тільки позначай групою `stripe`
- ❌ Не чіпай `config/payments.php` — секція `stripe` там залишається
- ❌ Не запускай `php artisan migrate` — жодних змін у БД
- ✅ Після кожного кроку — зупинись і покажи результат

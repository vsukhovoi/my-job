# МОДУЛЬ 2 (розгорнутий). Модель `Vacancy` — статуси, scopes, бізнес-методи

## 🎯 Мета модуля
Перетворити `App\Models\Vacancy` на повноцінний агрегат життєвого циклу: декларативні стани, безпечні переходи між ними, обчислювані атрибути для UI, scopes для запитів. Уся логіка статусів живе тут — і ніде більше.

**Передумова:** модуль 1 виконано та `php artisan migrate` запущено.

---

## 🔍 КРОК 2.1. Розвідка наявної моделі

Виведи мені повний поточний вміст:

```bash
cat app/Models/Vacancy.php
```

Я хочу побачити:
- `$fillable` — щоб знати, чи додавати нові поля туди
- `$casts` — щоб не дублювати
- Наявні scopes — раптом є `scopePublished` / `scopeActive` зі старою логікою
- Релейшни (`belongsTo Employer`, `hasMany Application` тощо) — щоб не зламати
- Імпорти на початку файлу

---

## 📐 КРОК 2.2. Структура змін

Я додаю в модель **п'ять блоків**:

1. Доповнення `$fillable` та `$casts`
2. Константи (`DEFAULT_PUBLICATION_DAYS`)
3. Scopes (5 штук)
4. Computed accessors (`days_left`, `hours_left`, `is_active`, `countdown_label`)
5. State-transition methods (`publish`, `extend`, `archive`, `expire`, `markNotificationSent`)

**Усе інше в моделі — не чіпай.**

---

## 📝 КРОК 2.3. Повна реалізація

### 2.3.1. Імпорти (додай угорі файлу)

```php
use App\Enums\VacancyStatus;            // створимо в модулі 3
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
```

### 2.3.2. Константа та `$fillable` / `$casts`

```php
class Vacancy extends Model
{
    /**
     * Скільки днів за замовчуванням триває одна публікація.
     */
    public const DEFAULT_PUBLICATION_DAYS = 30;

    protected $fillable = [
        // ... наявні поля — НЕ ВИДАЛЯЙ
        'published_at',
        'expires_at',
        'status',
        'expiry_notification_sent_at',
    ];

    protected $casts = [
        // ... наявні касти
        'published_at'                => 'datetime',
        'expires_at'                  => 'datetime',
        'expiry_notification_sent_at' => 'datetime',
        'status'                      => VacancyStatus::class,
    ];
```

> **Важливо:** `'status' => VacancyStatus::class` працює лише з модуля 3. Якщо створюєш модель ДО enum — тимчасово залиш `'status' => 'string'` і повернись сюди після модуля 3.

### 2.3.3. Scopes

```php
    /**
     * Активні вакансії: status=active, опубліковані, ще не expired.
     * Використовується на публічному лістингу.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', VacancyStatus::Active)
            ->whereNotNull('published_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Завершені — час вийшов, але ще не архівовані.
     * Залишаються доступні за прямим URL для SEO (модуль 9).
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', VacancyStatus::Expired);
    }

    /**
     * Чернетки — не показуються нікому, крім автора.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', VacancyStatus::Draft);
    }

    /**
     * Архів — повністю прибрані з пошуку та з прямих URL (404).
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', VacancyStatus::Archived);
    }

    /**
     * Активні вакансії, що завершаться найближчим часом.
     * Використовується модулем 8 (Nutgram-нотифікації).
     *
     * @param  int  $hours  у скільки годин від зараз шукаємо expires_at
     */
    public function scopeExpiringSoon(Builder $query, int $hours = 24): Builder
    {
        return $query
            ->where('status', VacancyStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours($hours)]);
    }

    /**
     * Активні + ще не надсилали сповіщення «скоро завершиться».
     * Захищає від дублікатів у Nutgram-команді.
     */
    public function scopePendingExpiryNotification(Builder $query, int $hours = 24): Builder
    {
        return $query->expiringSoon($hours)
            ->whereNull('expiry_notification_sent_at');
    }
```

### 2.3.4. Computed accessors (Laravel 9+ синтаксис)

```php
    /**
     * Чи вакансія активна ЗАРАЗ (з урахуванням expires_at).
     * Не дорівнює `$vacancy->status === VacancyStatus::Active`,
     * бо статус оновлюється раз на годину scheduler-ом.
     */
    protected function isActive(): Attribute
    {
        return Attribute::get(fn () =>
            $this->status === VacancyStatus::Active
            && $this->published_at !== null
            && ($this->expires_at === null || $this->expires_at->isFuture())
        );
    }

    /**
     * Скільки повних днів залишилось.
     * null — якщо вакансія не активна або немає expires_at.
     * 0 — менше доби, але ще не expired.
     */
    protected function daysLeft(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->is_active || ! $this->expires_at) {
                return null;
            }

            $diff = (int) now()->diffInDays($this->expires_at, absolute: false);

            return max($diff, 0);
        });
    }

    /**
     * Скільки повних годин залишилось (для відображення < 1 дня).
     */
    protected function hoursLeft(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->is_active || ! $this->expires_at) {
                return null;
            }

            return max((int) now()->diffInHours($this->expires_at, absolute: false), 0);
        });
    }

    /**
     * Користувацький лейбл «Залишилось N днів» з правильною українською плюралізацією.
     * Використовується Livewire-компонентом (модуль 7).
     */
    protected function countdownLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->status === VacancyStatus::Expired) {
                return 'Публікацію завершено';
            }

            if ($this->status === VacancyStatus::Archived) {
                return 'В архіві';
            }

            if ($this->status === VacancyStatus::Draft) {
                return 'Чернетка';
            }

            if (! $this->expires_at) {
                return 'Безстрокова публікація';
            }

            $hoursLeft = $this->hours_left ?? 0;

            // Менше години
            if ($hoursLeft < 1) {
                $minutes = max((int) now()->diffInMinutes($this->expires_at, absolute: false), 0);
                return "Залишилось {$minutes} " . self::pluralizeUk($minutes, 'хвилина', 'хвилини', 'хвилин');
            }

            // Менше доби
            if ($hoursLeft < 24) {
                return "Залишилось {$hoursLeft} " . self::pluralizeUk($hoursLeft, 'година', 'години', 'годин');
            }

            $days = $this->days_left;
            return "Залишилось {$days} " . self::pluralizeUk($days, 'день', 'дні', 'днів');
        });
    }

    /**
     * Українська плюралізація для чисел: 1 / 2-4 / 5+.
     * Працює і для 11-14 (там «днів», не «дні»).
     */
    private static function pluralizeUk(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
    }
```

### 2.3.5. State transitions

```php
    /**
     * Опублікувати вакансію вперше: status=Active, published_at=now, expires_at=now+$days.
     * Якщо вакансія вже Active — НЕ змінюємо published_at (це історичне поле).
     *
     * @throws \DomainException якщо вакансія Archived — її не можна публікувати без розархівації
     */
    public function publish(int $days = self::DEFAULT_PUBLICATION_DAYS): void
    {
        if ($this->status === VacancyStatus::Archived) {
            throw new \DomainException(
                "Вакансію #{$this->id} архівовано. Спочатку відновіть її."
            );
        }

        $this->forceFill([
            'status'       => VacancyStatus::Active,
            'published_at' => $this->published_at ?? now(),
            'expires_at'   => now()->addDays($days),
            // Скидаємо прапорець нотифікації — щоб новий цикл міг знову нагадати
            'expiry_notification_sent_at' => null,
        ])->save();
    }

    /**
     * Продовжити публікацію на $days днів.
     * Логіка:
     *   - якщо Active: expires_at += $days
     *   - якщо Expired: status=Active, expires_at = now+$days (не +до старого expires_at,
     *     бо клієнт платить ЗА ПЕРІОД, а не за повернення в минуле)
     *   - якщо Draft / Archived: кидаємо виняток
     *
     * Не оновлює published_at — це історичне поле.
     */
    public function extend(int $days): void
    {
        if (! in_array($this->status, [VacancyStatus::Active, VacancyStatus::Expired], true)) {
            throw new \DomainException(
                "Вакансію #{$this->id} не можна продовжити зі статусу {$this->status->value}."
            );
        }

        $newExpiresAt = $this->status === VacancyStatus::Expired
            ? now()->addDays($days)
            : ($this->expires_at ?? now())->addDays($days);

        $this->forceFill([
            'status'                      => VacancyStatus::Active,
            'expires_at'                  => $newExpiresAt,
            'expiry_notification_sent_at' => null, // новий цикл — нове нагадування
        ])->save();
    }

    /**
     * Архівувати вакансію — повністю прибрати з пошуку та з прямих URL (404).
     * Використовуй коли вакансія більше не актуальна назавжди.
     */
    public function archive(): void
    {
        $this->forceFill(['status' => VacancyStatus::Archived])->save();
    }

    /**
     * Позначити як завершену (виконує scheduler автоматично).
     * Залишається доступна за URL для SEO (модуль 9).
     */
    public function expire(): void
    {
        $this->forceFill(['status' => VacancyStatus::Expired])->save();
    }

    /**
     * Позначити, що сповіщення «скоро завершиться» було надіслане.
     * Викликається з модуля 8 (Nutgram).
     */
    public function markExpiryNotificationSent(): void
    {
        $this->forceFill(['expiry_notification_sent_at' => now()])->save();
    }
}
```

---

## ⚠️ Критичні нюанси

### 1. `forceFill` замість `update`
Я навмисно використовую `$this->forceFill([...])->save()` замість `$this->update([...])`. Чому:

- `update()` запускає observers і events, які можуть викликати каскад (наприклад, `Saving::class` логує зміни → той логер викликає Telegram-нотифікацію → ...).
- `forceFill` дає чіткий контроль: лише ці поля, без обходу `$fillable`.
- Якщо в проєкті є observer на `Vacancy` — обговоримо, чи має він спрацьовувати на life-cycle переходах.

### 2. Чому `is_active` ≠ `status === Active`
Між запусками scheduler-а (раз на годину) у БД лежать вакансії зі статусом `Active`, але `expires_at` уже в минулому. Користувач не повинен бачити їх як активні.

`is_active` робить **точну** перевірку «прямо зараз»; `scopeActive()` робить те саме на рівні БД. **Завжди** використовуй `$vacancy->is_active`, а не `$vacancy->status === VacancyStatus::Active`, у фронтенді.

### 3. Українська плюралізація — окремий метод
Не перенось у глобальний хелпер чи Service до того, як побачиш, що вона потрібна в 3+ місцях. Поки що — приватний static у моделі. Винесемо в `App\Support\UkrainianPlural` лише коли з'явиться 3-тє використання.

**Тестові випадки** для `pluralizeUk()`:
| n | one | few | many | Очікуване |
|---|-----|-----|------|-----------|
| 1 | день | дні | днів | день |
| 2 | день | дні | днів | дні |
| 5 | день | дні | днів | днів |
| 11 | день | дні | днів | днів *(не «дні»!)* |
| 21 | день | дні | днів | день |
| 22 | день | дні | днів | дні |
| 111 | день | дні | днів | днів |
| 121 | день | дні | днів | день |

**Покрий усі вісім кейсів у `tests/Unit/UkrainianPluralTest.php`** (модуль 10).

### 4. `extend()` для expired-вакансії
Реальний кейс: користувач платить за продовження через тиждень після того, як вакансія expired. Інтуїтивно очікується «вакансія знову активна на 30 днів від СЬОГОДНІ», а не «активна 30 днів від моменту експайру» (бо клієнт платить за актуальний період, а не за минулий).

Зафіксовано у тестах модуля 10 — `test('extend reactivates expired vacancy from now')`.

### 5. Не використовуй `Carbon::diffInDays()` без `absolute: false`
За замовчуванням Carbon повертає **абсолютне** значення (3 дні минуло І 3 дні в майбутньому повертають однакові 3). У нашому контексті expires_at буде в минулому → абсолютне значення дасть позитивний `days_left` — БАГ.

`absolute: false` повертає від'ємне число для минулих дат, а ми обрізаємо `max(..., 0)`.

### 6. Не використовуй observers для зміни статусів
Спокуса написати `VacancyObserver@saving` з логікою «якщо expires_at < now, то status = expired». **Не треба.** Це призведе до:
- Нескінченних циклів (saving → save → saving)
- Несумісності з `forceFill` методів `expire()` / `extend()`
- Складної дебажки

Усі переходи — лише через явні методи моделі. Scheduler — теж явний.

---

## ✅ Очікуваний результат модуля

1. Файл `app/Models/Vacancy.php` оновлено: усі п'ять блоків додано.
2. Швидка перевірка в tinker:

```bash
php artisan tinker
```

```php
// Створити чернетку
$v = Vacancy::factory()->create(['status' => 'draft']);

// Опублікувати на 30 днів
$v->publish(30);
dump($v->status, $v->published_at, $v->expires_at);

// Перевірити лічильник
dump($v->countdown_label);  // "Залишилось 30 днів"

// Продовжити
$v->extend(15);
dump($v->expires_at);  // +45 днів від сьогодні

// Перевірити плюралізацію
$v->expires_at = now()->addDays(2);
$v->save();
dump($v->countdown_label);  // "Залишилось 2 дні" — НЕ "днів"!

$v->expires_at = now()->addDays(11);
$v->save();
dump($v->countdown_label);  // "Залишилось 11 днів" — НЕ "дні"!
```

3. Звіт мені:
   ```
   Модель Vacancy оновлено.
   Додано: 5 scopes, 4 accessors, 5 state-методів, 1 константа.
   Перевірка через tinker пройшла. Перейти до модуля 3 (VacancyStatus enum)? (так/ні)
   ```

---

## 🚨 Чого НЕ робити

- ❌ Не пиши тести — модуль 10.
- ❌ Не створюй Filament-ресурс — модуль 5.
- ❌ Не торкайся `VacancyController` чи Volt-сторінок — модулі 7, 9.
- ❌ Не додавай Policy в цьому модулі — окремо.
- ❌ Не додавай scope `published()` як alias до `active()` — їх семантика різна, плутанина.

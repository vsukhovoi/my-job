# МОДУЛЬ 3. Enum `VacancyStatus`

## 🎯 Мета
PHP 8.1+ backed enum для статусів вакансії з лейблами, кольорами для Filament, Tailwind-класами і helper для Filament Select.

**Передумова:** модулі 1–2 виконано.

---

## 📂 Реалізація

`app/Enums/VacancyStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum VacancyStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Expired  = 'expired';
    case Archived = 'archived';

    /**
     * Локалізований лейбл для UI.
     */
    public function label(): string
    {
        return match($this) {
            self::Draft    => 'Чернетка',
            self::Active   => 'Активна',
            self::Expired  => 'Завершена',
            self::Archived => 'Архів',
        };
    }

    /**
     * Колір бейджа для Filament.
     */
    public function color(): string
    {
        return match($this) {
            self::Draft    => 'gray',
            self::Active   => 'success',
            self::Expired  => 'warning',
            self::Archived => 'danger',
        };
    }

    /**
     * Tailwind-класи для бейджа на фронтенді.
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::Draft    => 'bg-gray-100 text-gray-700',
            self::Active   => 'bg-green-100 text-green-700',
            self::Expired  => 'bg-yellow-100 text-yellow-700',
            self::Archived => 'bg-red-100 text-red-700',
        };
    }

    /**
     * Опис для адмінів — пояснює, що означає кожен статус.
     */
    public function description(): string
    {
        return match($this) {
            self::Draft    => 'Не опубліковано — бачить лише автор.',
            self::Active   => 'Активна публікація на сайті.',
            self::Expired  => 'Час публікації вийшов, доступна за прямим URL для SEO.',
            self::Archived => 'Знята з пошуку, повертає 404 за прямим URL.',
        };
    }

    /**
     * Масив для Filament Select / Form options.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
            ->toArray();
    }

    /**
     * Тільки публічно видимі статуси (для лістингів і пошуку).
     */
    public static function publicCases(): array
    {
        return [self::Active, self::Expired];
    }
}
```

---

## ⚠️ Нюанси

1. **`string` як backing type, а не `int`** — потім простіше дивитись у БД (`status = 'active'` зрозуміліше за `status = 2`).

2. **Не змінюй порядок case'ів** після того, як вони в БД. Якщо це enum БД-рівня (не наш випадок) — зміна порядку = міграція. У нас рядкові значення стабільні, але порядок впливає на `cases()` ітерацію.

3. **Не додавай case `Banned` чи `Hidden` без узгодження** — це міняє бізнес-логіку scopes.

4. **Чому окремий метод `description()`** — Filament v4 має `helperText()` у формах. Зручно показувати пояснення прямо біля Select.

---

## 🧪 Перевірка

```bash
php artisan tinker --execute="
    use App\Enums\VacancyStatus;
    dump(VacancyStatus::Active->label());           // 'Активна'
    dump(VacancyStatus::Active->color());           // 'success'
    dump(VacancyStatus::options());                 // ['draft' => 'Чернетка', ...]
    dump(VacancyStatus::Draft === VacancyStatus::from('draft'));  // true
"
```

---

## ✅ Результат

- Файл `app/Enums/VacancyStatus.php` створено.
- Модель `Vacancy` з модуля 2 коректно кастить `'status' => VacancyStatus::class`.
- Перейти до модуля 4 (Scheduler).

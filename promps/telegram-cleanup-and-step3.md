# Prompt для Claude Code: Прибрати застарілий тест-дубль (Крок 2.5) → повернути ліміт 110 (Крок 3)

> **Контекст:** My Job (Laravel 13.4 + PHP 8.3). Revert `5910765` виконано чисто (multi-channel повернуто). Лишилось 4 FAIL у `tests/Feature/Console/NotifyExpiringVacanciesCommandTest.php` — застарілий файл, що кличе неіснуючу команду `vacancies:notify-expiring` і неіснуюче поле `telegram_notifications_enabled`. Актуальний дубль: `tests/Feature/Commands/NotifyExpiringVacanciesTest.php`.
> **Строго по кроках зі СТОП-гейтами. Не force-push.** PHPUnit 12: `#[Test]`, `actingAs()` ДО `Volt::test()`.

---

## КРОК 2.5 — Прибрати застарілий тест-дубль

### Спершу перевірка (без видалення)
1. Показати назви всіх тест-методів у `tests/Feature/Console/NotifyExpiringVacanciesCommandTest.php` (4 шт).
2. Показати назви всіх тест-методів у `tests/Feature/Commands/NotifyExpiringVacanciesTest.php` (6 шт, актуальні).
3. Доповісти: чи КОЖЕН сценарій зі старого файлу покритий у новому (за змістом, не за дослівною назвою)? Якщо у старому є унікальний сценарій, якого НЕМА в новому — НЕ видаляти, показати його й питати.

**СТОП. Чекай підтвердження, що покриття не втрачається.**

### Після підтвердження
4. `git rm tests/Feature/Console/NotifyExpiringVacanciesCommandTest.php`.
5. Коміт: `chore(tests): прибрати застарілий NotifyExpiringVacanciesCommandTest (дубль, стара команда+поле)`.
6. Запустити повний набір. Очікувано: 392 passed, 0 failed (4 мертвих тести зникли).

**СТОП. Чекай підтвердження Кроку 3.**

---

## КРОК 3 — Повернути ЛИШЕ ліміт auth/status (окремий мінімальний коміт)
БЕЗ повторної централізації інших лімітів. Решта маршрутів лишають свої inline-throttle як після revert.
1. У `routes/api.php` для `GET /telegram/auth/status/{token}`: замінити `throttle:120,1` на `throttle:110,5` (110 запитів за 5-хв вікно = TTL 300с ÷ polling 3с + буфер).
2. У `tests/Feature/Security/TelegramSecurityTest.php`: оновити тест порогу — 110 дозволених, 111-й → 429 (revert повернув поріг 120, підняти до 110 тут).
3. Інші маршрути й ліміти — НЕ чіпати.
4. Коміт: `fix(telegram): підняти ліміт auth/status до 110 за 5-хв вікно (TTL сесії)`.
5. Повний набір. Вимога: 0 FAIL.

**СТОП. Фінал: `git log --oneline -5`, статус тестів, підсумкова дельта.**

---
**Правило:** жодних «заодно». Сумнів → СТОП.

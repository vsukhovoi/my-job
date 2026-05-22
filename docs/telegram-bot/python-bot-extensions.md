# Python Bot Extensions — Task Specification

Цей документ описує нові HTTP-endpoints, які потрібно реалізувати в Python-боті
для підтримки Telegram callback infrastructure (Laravel Phase 1).

**Поточний базовий URL:** `TELEGRAM_BOT_API_URL` (з `.env`, напр. `http://localhost:8001`)
**Автентифікація:** Laravel → Python: немає (внутрішня мережа); Python → Laravel: `X-Telegram-Webhook-Token`

---

## Існуючий endpoint (референс)

### `POST /send-message`

```json
// Request
{ "chat_id": 123456789, "text": "Привіт!" }

// Response 200
{ "ok": true }
```

---

## Нові endpoints (потрібно реалізувати)

### 1. `POST /send-message-with-keyboard`

Відправляє повідомлення з inline-клавіатурою Telegram.

```json
// Request
{
  "chat_id": 123456789,
  "text": "📨 Запит на співбесіду\n\nКомпанія ...",
  "parse_mode": "HTML",
  "inline_keyboard": [
    [
      { "text": "📝 Відповісти на питання", "url": "https://myjob.co.ua/interview-request/42/respond" }
    ]
  ]
}

// Response 200
{
  "message_id": 987,
  "chat_id": 123456789
}

// Response 4xx/5xx (якщо Telegram API повернув помилку)
{
  "error": "chat not found"
}
```

**Деталі реалізації:**
- Викликати `bot.send_message(chat_id, text, parse_mode=parse_mode, reply_markup=InlineKeyboardMarkup(...))`
- `inline_keyboard` — масив рядів, кожен рядок — масив кнопок
- Кнопки можуть мати `url` (посилання) або `callback_data` (HMAC-підписаний рядок ≤64 байти)
- Повернути `message_id` та `chat_id` із відповіді Telegram

---

### 2. `POST /edit-message`

Редагує текст (та опційно клавіатуру) вже надісланого повідомлення.

```json
// Request
{
  "chat_id": 123456789,
  "message_id": 987,
  "text": "✅ Відповідь отримана",
  "parse_mode": "HTML",
  "inline_keyboard": null
}

// Response 200
{ "ok": true }

// Response 200 (якщо повідомлення не змінилось — Telegram повертає 400, але це не помилка)
{ "ok": true, "note": "message_not_modified" }
```

**Деталі реалізації:**
- Викликати `bot.edit_message_text(chat_id, message_id, text, parse_mode, reply_markup)`
- Якщо `inline_keyboard` є `null` → не передавати `reply_markup` (зберегти існуючу клавіатуру)
- Якщо `inline_keyboard` є `[]` → передати `reply_markup=InlineKeyboardMarkup([])` (прибрати клавіатуру)
- **Не кидати виключення** при `MessageNotModified` або `MessageCantBeEdited` (старе повідомлення) — повертати `{ "ok": true }`

---

### 3. `POST /answer-callback`

Підтверджує отримання callback query. Обов'язково після кожного callback,
інакше у користувача буде "годинник" на кнопці.

```json
// Request
{
  "callback_query_id": "1234567890abcdef",
  "text": "Виконано!",
  "show_alert": false
}

// Response 200
{ "ok": true }
```

**Деталі реалізації:**
- Викликати `bot.answer_callback_query(callback_query_id, text, show_alert)`
- `text` може бути `null` — тоді тільки прибрати "годинник", без toast
- `show_alert: true` → модальне вікно замість toast
- Taймаут відповіді Telegram — 10 секунд від отримання callback. Виконувати цей виклик першим.

---

## Webhook: Python-бот → Laravel

### `POST /api/telegram/webhook/callback`

Коли Python-бот отримує `callback_query` від Telegram — він має відправити його в Laravel для обробки.

**URL:** `{APP_URL}/api/telegram/webhook/callback`
**Метод:** POST
**Заголовки:**
```
Content-Type: application/json
X-Telegram-Webhook-Token: {TELEGRAM_WEBHOOK_TOKEN}
```

```json
// Request body
{
  "telegram_user_id": 123456789,
  "callback_data": "respond:interview_request:42::aBcD1234",
  "message_id": 987,
  "callback_query_id": "1234567890abcdef"
}
```

```json
// Response 200 (Laravel прийняв)
{ "ok": true }

// Response 401 (неправильний токен)
{ "error": "Unauthorized" }

// Response 404 (користувач не знайдений за telegram_user_id)
{ "error": "User not found" }
```

**Деталі реалізації (Python-сторона):**
- Слухати `callback_query` handler у nutgram/python-telegram-bot
- При отриманні callback_query — **першим** зробити `answer_callback_query` (або делегувати Laravel)
- Відправити POST на Laravel-endpoint з payload вище
- Якщо Laravel повернув 2xx — все гаразд
- Якщо помилка — залогувати, але не ретраяти (щоб не дублювати обробку)

---

## Порядок обробки callback_query (загальний flow)

```
Telegram → Python-бот (callback_query)
    ↓
Python POST /api/telegram/webhook/callback → Laravel
    ↓
Laravel: TelegramWebhookController
    ├── Перевірити X-Telegram-Webhook-Token
    ├── Знайти User за telegram_user_id
    └── TelegramCallbackRouter::dispatch(callback_data, user, message_id, callback_query_id)
            ├── Rate limit check (30/min per user)
            ├── HMAC verify via CallbackDataSigner
            ├── Знайти відповідний handler (canHandle)
            ├── handler->handle(payload, user, message_id)
            └── answerCallbackQuery(callback_query_id)  ← POST Python /answer-callback
```

---

## Змінні середовища

| Змінна | Де | Опис |
|---|---|---|
| `TELEGRAM_BOT_API_URL` | Laravel `.env` | URL Python-бота (напр. `http://localhost:8001`) |
| `TELEGRAM_WEBHOOK_TOKEN` | Python `.env` + Laravel `.env` | Спільний секретний токен для webhook |
| `TELEGRAM_CALLBACK_SECRET` | Laravel `.env` | Секрет для HMAC підпису callback_data |

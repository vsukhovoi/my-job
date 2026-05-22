# Claude Code Prompt — Telegram Bot Python Service (Phase 1B-basic)

**Проєкт:** My Job (myjob.co.ua)
**Тип:** Створення нового сервісу з нуля (Python-бот)
**Зв'язок:** Закриває Python-частину Phase 1 (Laravel infrastructure уже готова, 412/412 PASS)
**Скоп:** Phase 1B-basic — HTTP API endpoints + Telegram webhook + `/start` + Telegram auth flow

---

## Контекст

Laravel-частина Phase 1 завершена (22.05.2026):
- `TelegramNotifier` сервіс з методами `sendMessage`, `sendMessageWithKeyboard`, `editMessageText`, `answerCallbackQuery` — усі викликають `Http::post()` на `TELEGRAM_BOT_API_URL`
- `TelegramCallbackRouter` + `CallbackDataSigner` (HMAC) — обробка callback_query від Telegram
- Webhook endpoint `POST /api/telegram/webhook/callback` з захистом через `X-Telegram-Webhook-Token` header
- Deep-link routes `interview.respond` + `interview.view`
- Документація: `docs/telegram-bot/python-bot-extensions.md` + `docs/telegram-bot/callback-protocol.md`

**Проблема:** Python-бота **не існує** — TELEGRAM_BOT_API_URL не задано, контейнера у docker-compose немає, усі виклики Laravel падають у catch.

**Бот зареєстрований у BotFather** як `@myjob_in_bot`, токен має бути в `.env` (`TELEGRAM_BOT_TOKEN`).

---

## 🛑 КРИТИЧНІ ПРАВИЛА

1. **Папка `/bot`** у корені поточного Laravel-репо (монорепо)
2. **Python 3.12** + **python-telegram-bot v21** + **FastAPI** + **httpx**
3. **Docker service name** — `bot` у `docker-compose.yml`. Laravel звертається через `http://bot:8080` (Docker DNS)
4. **Subdomain `bot.myjob.co.ua`** для Telegram webhook через Nginx + Cloudflare
5. **НЕ використовувати** `localhost:8080` (порт зайнятий aigent-reverb-1 на VPS)
6. **HMAC-сумісність:** Python-бот **НЕ** валідує/підписує callback_data — це робить Laravel `CallbackDataSigner`. Python просто форвардить raw callback_data
7. **UA-копірайтинг:** усі повідомлення бота українською, без слів про "посередництво у працевлаштуванні"
8. **Безпека:** обидва напрямки HTTP мають shared secret
   - Laravel → Bot: `Authorization: Bearer {BOT_API_TOKEN}` (новий env)
   - Bot → Laravel: `X-Telegram-Webhook-Token: {value}` (вже існує в Laravel)
9. **НЕ створювати** `/alerts`, розсилки, або інші не-Phase 1 фічі — це скоп майбутніх фаз
10. **Тести:** pytest + httpx.AsyncClient для integration tests + unittest.mock для Telegram API

---

## 🔍 КРОК 1 — Reconnaissance (READ-ONLY)

### 1.1. Прочитати Laravel-сторону протоколу
**Обов'язкові файли (читати уважно — це договір між сервісами):**
- `docs/telegram-bot/python-bot-extensions.md` — JSON-схеми 4 endpoints
- `docs/telegram-bot/callback-protocol.md` — формат callback_data, HMAC, security
- `app/Services/TelegramNotifier.php` — які саме payload відправляються
- `app/Http/Controllers/Api/TelegramWebhookController.php` — що очікується від бота
- `app/Services/Telegram/CallbackDataSigner.php` — формат callback_data
- `app/Services/TelegramAuthService.php` — flow авторизації
- `app/Http/Controllers/TelegramAuthController.php` — endpoints для auth (особливо: куди бот шле телефон/contact)

### 1.2. Прочитати інфраструктуру
- `docker-compose.yml` — наявні сервіси, мережа, volumes, наявні порти
- `.env.example` — поточні env-змінні (особливо: TELEGRAM_BOT_USERNAME, TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_TOKEN, TELEGRAM_CALLBACK_SECRET)
- `nginx/` або `docker/nginx/` (де б не знаходився конфіг) — як зараз налаштовані server blocks для основного домену
- `config/services.php` — секція telegram

### 1.3. Прочитати схему даних для auth
- `app/Models/User.php` — поля `telegram_id`, `phone`, `notification_channel`
- Міграцію, що додавала `telegram_id` (для розуміння типу поля — int / bigInt / string)
- `app/Services/TelegramAuthService.php` — як саме генерується login token, як працює polling

### ⏸ STOP-GATE 1
Вивести **резюме протоколу** у форматі:
```
HTTP API (Laravel → Bot) — 4 endpoints:
1. POST /send-message
   Input: {chat_id, text, parse_mode}
   Output: {success, message_id?, error?}
2. POST /send-message-with-keyboard
   Input: {chat_id, text, parse_mode, inline_keyboard}
   Output: {success, message_id?, error?}
3. POST /edit-message
   Input: {chat_id, message_id, text, parse_mode, inline_keyboard?}
   Output: {success, error?}
4. POST /answer-callback
   Input: {callback_query_id, text?, show_alert}
   Output: {success, error?}

Webhook (Bot → Laravel):
POST /api/telegram/webhook/callback
Header: X-Telegram-Webhook-Token: {token}
Body: {telegram_user_id, callback_data, message_id, callback_query_id}

Auth flow:
[опис: користувач натискає кнопку на сайті → ... → polling → login token]
```

**Зупинитись.** Чекати "go".

---

## 🛠 КРОК 2 — Структура проєкту

Створити папку `/bot/` з такою структурою:

```
bot/
├── Dockerfile
├── pyproject.toml          # Poetry або pip-tools для deps
├── README.md               # коротка інструкція deploy
├── .env.example            # шаблон env для бота
├── .dockerignore
├── src/
│   ├── __init__.py
│   ├── main.py             # FastAPI app + lifecycle
│   ├── config.py           # Settings через pydantic-settings
│   ├── telegram_client.py  # обгортка над python-telegram-bot
│   ├── laravel_client.py   # httpx-клієнт для викликів Laravel
│   ├── handlers/
│   │   ├── __init__.py
│   │   ├── commands.py     # /start
│   │   ├── contacts.py     # contact share для auth
│   │   └── callbacks.py    # forwarding callback_query до Laravel
│   ├── api/
│   │   ├── __init__.py
│   │   ├── deps.py         # FastAPI dependencies (auth)
│   │   ├── schemas.py      # Pydantic models для request/response
│   │   └── routes.py       # 4 HTTP endpoints
│   ├── middleware/
│   │   ├── __init__.py
│   │   └── auth.py         # Bearer token check для Laravel→Bot
│   └── utils/
│       ├── __init__.py
│       └── logging.py      # structured logging
└── tests/
    ├── __init__.py
    ├── conftest.py
    ├── test_api_send_message.py
    ├── test_api_send_with_keyboard.py
    ├── test_api_edit_message.py
    ├── test_api_answer_callback.py
    ├── test_webhook_start_command.py
    ├── test_webhook_callback_forwarding.py
    └── test_webhook_contact_share.py
```

---

## 🛠 КРОК 3 — Конфігурація та залежності

### 3.1. `pyproject.toml`
**Залежності (production):**
- `python = "^3.12"`
- `python-telegram-bot[webhooks] = "^21.0"` — основна бібліотека з підтримкою webhook
- `fastapi = "^0.115"`
- `uvicorn[standard] = "^0.32"`
- `httpx = "^0.27"`
- `pydantic = "^2.9"`
- `pydantic-settings = "^2.6"`
- `python-multipart = "^0.0.20"` (якщо потрібно)

**Залежності (dev):**
- `pytest = "^8.0"`
- `pytest-asyncio = "^0.24"`
- `pytest-httpx = "^0.32"` (mock httpx requests)
- `ruff = "^0.7"` (linter)
- `mypy = "^1.13"`

### 3.2. `src/config.py` (pydantic-settings)
Поля Settings (читаються з env):
- `TELEGRAM_BOT_TOKEN: str` — токен від BotFather (required)
- `TELEGRAM_BOT_USERNAME: str = "myjob_in_bot"`
- `BOT_API_TOKEN: str` — secret для перевірки `Authorization: Bearer` від Laravel (required)
- `LARAVEL_API_URL: str = "http://app:80"` — куди бот шле webhook (Docker service name `app` або як називається Laravel-сервіс)
- `LARAVEL_WEBHOOK_TOKEN: str` — той самий що `TELEGRAM_WEBHOOK_TOKEN` в Laravel — для X-Telegram-Webhook-Token header (required)
- `TELEGRAM_WEBHOOK_SECRET: str` — secret для шляху webhook (Telegram надсилає на `/telegram/webhook/{secret}`) (required)
- `PUBLIC_URL: str` — `https://bot.myjob.co.ua` — для встановлення webhook у Telegram
- `LOG_LEVEL: str = "INFO"`
- `ENV: Literal["local", "production"] = "production"`

### 3.3. `.env.example`
Сформувати з усіх вищевказаних полів, з коментарями українською мовою і прикладами безпечних значень (для secret — нагадування про генерацію через `openssl rand -hex 32`).

### 3.4. Оновити Laravel `.env.example`
Додати/підтвердити:
```
TELEGRAM_BOT_API_URL=http://bot:8080  # Docker service name
TELEGRAM_BOT_API_TOKEN=<random 32+ chars>  # bearer token для Authorization header при викликах бота
```

### ⏸ STOP-GATE 2
Перерахувати усі env-змінні з обох сторін (Laravel + Bot) і зазначити, які значення мають збігатись:
```
ЗБІГАЮТЬСЯ (shared secrets):
- Laravel TELEGRAM_WEBHOOK_TOKEN == Bot LARAVEL_WEBHOOK_TOKEN
- Laravel TELEGRAM_BOT_API_TOKEN == Bot BOT_API_TOKEN

ОКРЕМІ (різні значення):
- TELEGRAM_BOT_TOKEN (тільки бот, від BotFather)
- TELEGRAM_WEBHOOK_SECRET (тільки бот, для path webhook)
- TELEGRAM_CALLBACK_SECRET (тільки Laravel, для HMAC)
```

---

## 🛠 КРОК 4 — HTTP API (Laravel → Bot)

### 4.1. `src/middleware/auth.py`
FastAPI dependency, що перевіряє `Authorization: Bearer {BOT_API_TOKEN}` header. При невідповідності — `HTTPException(401)`.

### 4.2. `src/api/schemas.py`
Pydantic-моделі для **усіх** request/response endpoints (відповідно до резюме з STOP-GATE 1).

**Inline keyboard:** використати тип, сумісний з форматом Telegram Bot API:
```python
class InlineButton(BaseModel):
    text: str
    callback_data: str | None = None
    url: str | None = None

class InlineKeyboard(BaseModel):
    inline_keyboard: list[list[InlineButton]]
```

### 4.3. `src/api/routes.py`
**4 endpoints**, усі з `Depends(verify_bearer_token)`:

#### `POST /send-message`
- Викликає `bot.send_message(chat_id, text, parse_mode)`
- Повертає `{success: True, message_id: int}` або `{success: False, error: str}`
- Обробка Telegram errors: `BadRequest`, `Forbidden` (юзер заблокував бота), `TelegramError`

#### `POST /send-message-with-keyboard`
- Конвертація `InlineKeyboard` → `telegram.InlineKeyboardMarkup`
- Викликає `bot.send_message(chat_id, text, parse_mode, reply_markup=keyboard)`

#### `POST /edit-message`
- Викликає `bot.edit_message_text(chat_id, message_id, text, parse_mode, reply_markup?)`
- **Безпечна обробка:** якщо повідомлення видалене, або тексти однакові — повертати `{success: False, error: "..."}` без exception

#### `POST /answer-callback`
- Викликає `bot.answer_callback_query(callback_query_id, text?, show_alert)`

**УВАГА:** усі endpoints мають **structured logging**: метод, chat_id (де є), результат (success/error), timing.

### 4.4. `src/telegram_client.py`
Singleton/dependency, що тримає `telegram.Bot` instance ініціалізований на старті FastAPI. Не створювати новий Bot на кожен request.

---

## 🛠 КРОК 5 — Telegram webhook (Telegram → Bot)

### 5.1. Налаштування webhook на старті
У FastAPI lifespan event:
1. На старті:
   - Виклик `bot.set_webhook(url=f"{PUBLIC_URL}/telegram/webhook/{TELEGRAM_WEBHOOK_SECRET}", allowed_updates=["message", "callback_query"])`
   - Логування результату
2. На зупинці:
   - Опційно `bot.delete_webhook()` (тільки для local env, не для production)

### 5.2. Webhook endpoint
**`POST /telegram/webhook/{secret_path}`**
- Перевіряє `secret_path == TELEGRAM_WEBHOOK_SECRET` — інакше 404
- Парсить body як `telegram.Update.de_json(body, bot)`
- Dispatches до handler за типом update:
  - `message.text == "/start"` або `message.text.startswith("/start ")` → `handlers/commands.py::handle_start`
  - `message.contact` → `handlers/contacts.py::handle_contact_share`
  - `update.callback_query` → `handlers/callbacks.py::handle_callback`
- Повертає `200 OK` завжди (Telegram retry'ить якщо не 200)

### 5.3. `handlers/commands.py` — `/start`
**Логіка:**

**Випадок A — простий `/start` (без payload):**
- Привітання UA-копірайтингом:
  ```
  Вітаю! Це бот платформи My Job.
  
  Я надсилаю сповіщення про:
  • нові вакансії за вашими підписками
  • заявки на ваші вакансії
  • запити на співбесіди та відповіді
  
  Щоб увійти у свій акаунт через Telegram — натисніть кнопку на сайті myjob.co.ua, я вам допоможу.
  ```
- БЕЗ кнопок

**Випадок B — `/start auth_<token>` (deep link для авторизації):**
- Витягти `auth_token` з тексту команди
- Зберегти `(telegram_user_id, auth_token)` у in-memory dict або Redis (TTL 5 хв) для подальшого зв'язку з contact share
- Відповісти:
  ```
  Для входу у акаунт поділіться, будь ласка, своїм номером телефону.
  Це безпечно — ми використаємо його тільки для прив'язки до існуючого профілю.
  ```
- Кнопка `KeyboardButton(text="📱 Поділитися номером", request_contact=True)` (звичайна reply keyboard, НЕ inline)

### 5.4. `handlers/contacts.py` — Contact share
**Логіка:**
1. Отримати `message.contact.phone_number` (формат: `+380501234567` або без `+`)
2. Знайти зв'язаний `auth_token` для цього `telegram_user_id` з кешу
3. Якщо токен знайдено:
   - Виклик Laravel endpoint `POST /api/telegram/auth/verify` (або як він називається — узгодити з reconnaissance) з payload:
     ```json
     {
       "auth_token": "...",
       "telegram_user_id": 123,
       "phone": "+380501234567",
       "first_name": "Іван",
       "last_name": "Петренко",
       "username": "ivanp"
     }
     ```
   - Header: `X-Telegram-Webhook-Token: {LARAVEL_WEBHOOK_TOKEN}`
4. Відповідь Laravel:
   - Якщо `200 + matched`: повідомити юзера "✅ Вхід підтверджено. Поверніться на сайт."
   - Якщо `200 + not_matched`: "⚠️ Цей номер не зареєстрований на My Job. Спочатку зареєструйтеся на сайті."
   - Якщо `4xx/5xx`: "❌ Сталася помилка. Спробуйте пізніше або зверніться у підтримку."
5. **Завжди** прибрати reply keyboard через `ReplyKeyboardRemove()`

**УВАГА:** точний URL та payload Laravel-endpoint узгодити з reconnaissance (КРОК 1.3). Якщо існуючий endpoint має інший контракт — адаптуватись, **не змінюючи Laravel-сторону**.

### 5.5. `handlers/callbacks.py` — Callback forwarding
**Логіка:**
1. Отримати `update.callback_query.data` (raw HMAC-підписаний рядок)
2. Витягти:
   - `telegram_user_id = update.callback_query.from_user.id`
   - `message_id = update.callback_query.message.message_id`
   - `callback_query_id = update.callback_query.id`
   - `callback_data = update.callback_query.data`
3. Викликати Laravel:
   ```
   POST {LARAVEL_API_URL}/api/telegram/webhook/callback
   Header: X-Telegram-Webhook-Token: {LARAVEL_WEBHOOK_TOKEN}
   Body: {telegram_user_id, callback_data, message_id, callback_query_id}
   ```
4. Не обробляти результат — Laravel сам викличе `/answer-callback` через HTTP API, якщо треба
5. Логувати timing та результат

**ВАЖЛИВО:** бот **НЕ парсить** і **НЕ валідує** callback_data — це HMAC-підписаний opaque payload. Усе валідуванна — на Laravel-стороні (`CallbackDataSigner::verify`).

---

## 🛠 КРОК 6 — Docker та deployment

### 6.1. `bot/Dockerfile`
- Базовий образ: `python:3.12-slim`
- Multi-stage build: builder (poetry install) + runtime
- Non-root user
- Health check: `curl -f http://localhost:8080/health || exit 1`
- ENTRYPOINT: `uvicorn src.main:app --host 0.0.0.0 --port 8080 --workers 2`

### 6.2. Додати `/health` endpoint у `src/api/routes.py`
- БЕЗ авторизації
- Повертає `{status: "ok", bot_username: "..."}` після перевірки `bot.get_me()` (з кешуванням)

### 6.3. Оновити `docker-compose.yml`
Додати сервіс:
```yaml
bot:
  build: ./bot
  container_name: myjob-bot
  restart: unless-stopped
  env_file:
    - ./bot/.env
  networks:
    - default  # або яка є основна
  depends_on:
    - app  # або як називається Laravel-сервіс
  expose:
    - "8080"
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
    interval: 30s
    timeout: 5s
    retries: 3
```

**УВАГА:** `expose`, НЕ `ports` — бот не повинен бути доступний з хоста напряму, тільки через Nginx reverse proxy.

### 6.4. Nginx server block
**Файл:** створити або оновити `nginx/sites-available/bot.myjob.co.ua.conf` (або де лежать конфіги — узгодити з reconnaissance інфраструктури).

```nginx
server {
    listen 443 ssl http2;
    server_name bot.myjob.co.ua;
    
    # SSL — використати існуючий wildcard або окремий cert
    
    # Telegram webhook IPs allowlist (опційно, але рекомендовано)
    # https://core.telegram.org/bots/webhooks#the-short-version
    
    location / {
        proxy_pass http://bot:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name bot.myjob.co.ua;
    return 301 https://$server_name$request_uri;
}
```

### 6.5. `bot/README.md`
Коротка інструкція з deploy:
1. Зареєструвати бота у BotFather, отримати `TELEGRAM_BOT_TOKEN`
2. Згенерувати secrets: `openssl rand -hex 32` × 4 (BOT_API_TOKEN, LARAVEL_WEBHOOK_TOKEN, TELEGRAM_WEBHOOK_SECRET, TELEGRAM_CALLBACK_SECRET) — узгодити з Laravel
3. Створити DNS-запис `bot.myjob.co.ua` → IP VPS, увімкнути Cloudflare proxy
4. Додати SSL cert (Let's Encrypt через certbot, або wildcard)
5. `docker compose up -d bot`
6. Перевірити webhook: `curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo`
7. Тест: написати `/start` боту в Telegram

---

## 🧪 КРОК 7 — Тести

Створити **7 тестів** з pytest:

1. **`test_api_send_message.py`** — успішне надсилання + 401 без bearer token + обробка Telegram error
2. **`test_api_send_with_keyboard.py`** — правильний keyboard в payload до Telegram API (через `pytest-httpx`)
3. **`test_api_edit_message.py`** — successful edit + graceful handle "message not modified"
4. **`test_api_answer_callback.py`** — з show_alert та без
5. **`test_webhook_start_command.py`** — простий `/start` (без payload) + `/start auth_{token}` (з payload)
6. **`test_webhook_contact_share.py`** — повний flow: contact → Laravel call → reply
7. **`test_webhook_callback_forwarding.py`** — callback_query → POST на Laravel з правильним header

**Конвенції:**
- pytest async через `pytest-asyncio`
- mock'и httpx через `pytest-httpx`
- mock'и Telegram API через monkey-patching `bot.send_message` тощо (НЕ реальні Telegram-виклики)
- Кожен тест ізольований (без shared state)

### ⏸ STOP-GATE 7
Запустити `cd bot && pytest -v`.
Очікуваний результат: **7/7 PASS**.

---

## 🧪 КРОК 8 — Integration test з реальним Laravel

### 8.1. Local integration test (smoke)
**Запустити в Docker:**
```bash
docker compose up -d
docker compose logs bot --follow
```

**Перевірити:**
1. Bot контейнер запустився та залогував `Webhook set: https://bot.myjob.co.ua/telegram/webhook/...`
2. Endpoint `GET http://bot:8080/health` повертає 200 (з Laravel-контейнера)
3. Laravel `TelegramNotifier::sendMessage()` працює end-to-end через `php artisan tinker`:
   ```php
   app(TelegramNotifier::class)->sendMessage(YOUR_TELEGRAM_ID, "Тест");
   ```
   → повідомлення приходить у Telegram

### 8.2. Manual checklist для production deployment
Створити файл `bot/DEPLOYMENT_CHECKLIST.md`:
- [ ] DNS bot.myjob.co.ua → VPS IP
- [ ] Cloudflare proxy enabled
- [ ] SSL cert встановлений
- [ ] Усі env-змінні згенеровані та узгоджені між Laravel та Bot
- [ ] `docker compose up -d bot`
- [ ] `getWebhookInfo` повертає правильний URL
- [ ] `/start` команда відповідає у Telegram
- [ ] Тестовий callback з Laravel → видно в логах бота
- [ ] Тестовий вхід через "Увійти через Telegram" → user отримує auth-prompt → contact share → редірект на сайт

---

## 📋 ФІНАЛЬНИЙ ЗВІТ

```
═══════════════════════════════════════════
PHASE 1B — Python Bot Service Created
═══════════════════════════════════════════

СТРУКТУРА:
- bot/ — 28+ файлів (src + tests + Docker)
- pyproject.toml: Python 3.12, python-telegram-bot 21, FastAPI

ENDPOINTS:
- HTTP API (Laravel→Bot): 4 endpoints, усі з Bearer auth
- Telegram webhook (Telegram→Bot): /telegram/webhook/{secret}
- Health: /health (public)

КОМАНДИ БОТА:
- /start — привітання
- /start auth_<token> — deep link для авторизації через Telegram
- Contact share handler — flow завершення авторизації

ТЕСТИ:
- 7/7 pytest PASS

DEPLOYMENT:
- docker-compose.yml: додано сервіс `bot`
- nginx config: створено для bot.myjob.co.ua
- DEPLOYMENT_CHECKLIST.md: manual steps для production

ENV-ЗМІННІ (нові):
Laravel:
- TELEGRAM_BOT_API_URL=http://bot:8080
- TELEGRAM_BOT_API_TOKEN=<генерувати>

Bot:
- TELEGRAM_BOT_TOKEN=<від BotFather>
- TELEGRAM_BOT_USERNAME=myjob_in_bot
- BOT_API_TOKEN=<те ж, що Laravel TELEGRAM_BOT_API_TOKEN>
- LARAVEL_API_URL=http://app:80
- LARAVEL_WEBHOOK_TOKEN=<те ж, що Laravel TELEGRAM_WEBHOOK_TOKEN>
- TELEGRAM_WEBHOOK_SECRET=<генерувати>
- PUBLIC_URL=https://bot.myjob.co.ua

OUT-OF-SCOPE (для наступних фаз):
- /alerts команда (Phase 2.1)
- Розсилка вакансій SendVacancyAlerts (Phase 2)
- Усі callback handlers на стороні Laravel (Phase 2/3)
- Telegram Stars / payments (Phase 5)
```

---

## 🚫 Що НЕ робити в цьому промпті

- НЕ створювати команду `/alerts` (це Phase 2.1)
- НЕ створювати розсилку вакансій (це Phase 2)
- НЕ створювати конкретних callback handlers на стороні Laravel — тільки forwarding
- НЕ міняти Laravel-код (тільки `.env.example` доповнити)
- НЕ робити commit / push / PR — після завершення чекати інструкцій
- НЕ запускати реальні Telegram API виклики у тестах
- НЕ створювати окремий git repo — все в монорепо

---

**Готовність:** Прочитати весь промпт. Розпочати з КРОКУ 1 (Reconnaissance). На кожному STOP-GATE — повний звіт і очікування підтвердження.

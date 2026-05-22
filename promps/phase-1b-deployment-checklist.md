# Phase 1B Deployment Checklist

**Мета:** розгорнути Python-бот (`/bot/`) на VPS та провалідувати E2E flow з реальним Telegram.

**Орієнтовний час:** 45-90 хв (залежно від наявності SSL setup'у та DNS propagation).

**Pre-requisites:**
- ✅ Phase 1 + 1B код у репо
- ✅ Доступ до OVH VPS (141.95.86.104) по SSH
- ✅ Доступ до Cloudflare dashboard для myjob.co.ua
- ✅ Доступ до @BotFather у Telegram
- ✅ Особистий Telegram акаунт для тестування

---

## 🧭 Етап 1 — Підготовка secrets (локально)

### 1.1. Згенерувати усі secrets
На локальній машині (НЕ на VPS):

```bash
echo "TELEGRAM_BOT_API_TOKEN=$(openssl rand -hex 32)"
echo "TELEGRAM_WEBHOOK_TOKEN=$(openssl rand -hex 32)"
echo "TELEGRAM_WEBHOOK_SECRET=$(openssl rand -hex 32)"
echo "TELEGRAM_CALLBACK_SECRET=$(openssl rand -hex 32)"
```

Зберегти в безпечному місці (1Password / Bitwarden / `.env.local.backup` поза git'ом).

### 1.2. Отримати TELEGRAM_BOT_TOKEN з BotFather
- Відкрити @BotFather у Telegram
- `/mybots` → обрати `@myjob_in_bot` → `API Token`
- Скопіювати токен (формат: `123456789:ABC-DEF...`)
- **Якщо токен раніше використовувався у тестах** — рекомендую revoke + новий, щоб точно бути впевненим у чистоті

### ☑ Чекпойнт 1
У тебе є **5 secrets** на руках:
- `TELEGRAM_BOT_TOKEN` (від BotFather)
- `TELEGRAM_BOT_API_TOKEN` (shared — Laravel ↔ Bot Bearer)
- `TELEGRAM_WEBHOOK_TOKEN` (shared — Laravel ↔ Bot, header X-Telegram-Webhook-Token)
- `TELEGRAM_WEBHOOK_SECRET` (тільки Bot, для path Telegram webhook)
- `TELEGRAM_CALLBACK_SECRET` (тільки Laravel, для HMAC callback_data)

---

## 🌐 Етап 2 — DNS налаштування

### 2.1. Cloudflare → DNS → Add record
- **Type:** A
- **Name:** bot
- **IPv4 address:** 141.95.86.104
- **Proxy status:** Proxied (🟠 — увімкнути Cloudflare proxy)
- **TTL:** Auto

### 2.2. Cloudflare → SSL/TLS → Overview
- **Encryption mode:** Full (strict) — рекомендовано
- (Якщо інша платформа використовує Flexible — залишити поточне, але це менш безпечно)

### 2.3. Verify
```bash
dig bot.myjob.co.ua +short
# Має повернути Cloudflare IPs (104.x.x.x або 172.x.x.x), не 141.95.86.104 напряму
```

Propagation: 1-5 хв з Cloudflare.

### ☑ Чекпойнт 2
`bot.myjob.co.ua` резолвиться. DNS лежить через Cloudflare.

---

## 🔒 Етап 3 — SSL cert на VPS

### 3.1. SSH на VPS
```bash
ssh user@141.95.86.104
```

### 3.2. Перевірити поточний SSL setup
Як зараз отримується SSL для основного `myjob.co.ua`?

**Якщо certbot/Let's Encrypt:**
```bash
sudo certbot certonly --nginx -d bot.myjob.co.ua
```
Або якщо там DNS-validation (через wildcard):
```bash
sudo certbot certonly --manual --preferred-challenges dns -d bot.myjob.co.ua
```

**Якщо wildcard `*.myjob.co.ua`** вже існує → нічого нового не треба, той самий cert покриває subdomain.

**Якщо Cloudflare Origin Certificate** (full-strict mode):
- Cloudflare dashboard → SSL/TLS → Origin Server → Create Certificate
- Hostname: `*.myjob.co.ua, myjob.co.ua`
- Validity: 15 років (стандарт Cloudflare Origin)
- Зберегти `.pem` + `.key` на VPS у `/etc/ssl/cloudflare/`

### 3.3. Verify
```bash
sudo certbot certificates
# або
ls -la /etc/letsencrypt/live/bot.myjob.co.ua/
```

### ☑ Чекпойнт 3
SSL cert для `bot.myjob.co.ua` доступний на VPS.

---

## 📥 Етап 4 — Pull code на VPS

### 4.1. Перейти в проєкт
```bash
cd /path/to/myjob  # узгодити з твоєю реальною структурою
```

### 4.2. Git pull
```bash
git pull origin main
# Або, якщо Phase 1B у feature-гілці:
# git fetch && git checkout feature/telegram-bot-phase-1b
```

### 4.3. Verify
```bash
ls bot/
# Має містити: src/, tests/, Dockerfile, pyproject.toml, .env.example, DEPLOYMENT_CHECKLIST.md
```

### ☑ Чекпойнт 4
Код на VPS, папка `bot/` існує.

---

## ⚙️ Етап 5 — Конфігурація .env файлів

### 5.1. Laravel .env (основний проєкт)
```bash
nano .env  # або vim, як зручно
```

Додати/оновити:
```
TELEGRAM_BOT_API_URL=http://bot:8080
TELEGRAM_BOT_API_TOKEN=<значення з чекпойнта 1>
TELEGRAM_WEBHOOK_TOKEN=<значення з чекпойнта 1>
TELEGRAM_CALLBACK_SECRET=<значення з чекпойнта 1>
TELEGRAM_BOT_USERNAME=myjob_in_bot
```

### 5.2. Bot .env (новий файл)
```bash
cp bot/.env.example bot/.env
nano bot/.env
```

Заповнити:
```
TELEGRAM_BOT_TOKEN=<від BotFather>
TELEGRAM_BOT_USERNAME=myjob_in_bot
BOT_API_TOKEN=<те саме значення, що Laravel TELEGRAM_BOT_API_TOKEN>
LARAVEL_API_URL=http://nginx:8080
LARAVEL_WEBHOOK_TOKEN=<те саме значення, що Laravel TELEGRAM_WEBHOOK_TOKEN>
TELEGRAM_WEBHOOK_SECRET=<згенероване значення>
PUBLIC_URL=https://bot.myjob.co.ua
LOG_LEVEL=INFO
ENV=production
```

### 5.3. Sanity check shared secrets
```bash
# Перевірити, що значення збігаються
grep TELEGRAM_BOT_API_TOKEN .env
grep BOT_API_TOKEN bot/.env
# Має бути ОДНЕ І ТЕ Ж значення

grep TELEGRAM_WEBHOOK_TOKEN .env
grep LARAVEL_WEBHOOK_TOKEN bot/.env
# Має бути ОДНЕ І ТЕ Ж значення
```

### ☑ Чекпойнт 5
Обидва `.env` заповнені, shared secrets збігаються.

---

## 🌍 Етап 6 — Nginx config для bot.myjob.co.ua

### 6.1. Скопіювати reference config
```bash
sudo cp docker/nginx/bot.myjob.co.ua.conf /etc/nginx/sites-available/bot.myjob.co.ua.conf
```

(Точний шлях reference config — узгодити з фактичним розташуванням у репо. Може бути `bot/nginx/` або `docker/nginx/`.)

### 6.2. Адаптувати SSL шляхи у конфігу
```bash
sudo nano /etc/nginx/sites-available/bot.myjob.co.ua.conf
```

Перевірити та відкоригувати:
- `ssl_certificate` шлях до .pem
- `ssl_certificate_key` шлях до .key
- `proxy_pass http://bot:8080` — переконатись, що Nginx запущений на хості і має доступ до Docker network (або Nginx у тому ж compose — тоді ім'я сервісу працює напряму)

**Якщо Nginx на хості (НЕ в Docker):**
Замінити `proxy_pass http://bot:8080;` на `proxy_pass http://127.0.0.1:<host_port>;`, і у `docker-compose.yml` для сервісу `bot` додати `ports: ["127.0.0.1:18080:8080"]` (наприклад 18080 на хості).

**Якщо Nginx у Docker compose як інший сервіс** — `proxy_pass http://bot:8080;` працює через Docker DNS.

### 6.3. Активувати config
```bash
sudo ln -s /etc/nginx/sites-available/bot.myjob.co.ua.conf /etc/nginx/sites-enabled/
sudo nginx -t  # перевірка синтаксису
sudo systemctl reload nginx
```

### 6.4. Verify
```bash
curl -I https://bot.myjob.co.ua/
# Очікувано: 502 Bad Gateway (бо контейнер ще не запущений) АБО 404 (якщо нічого не слухає)
# Якщо connection refused / no route to host — Nginx неправильно налаштований
# Якщо SSL handshake error — проблема з certs
```

### ☑ Чекпойнт 6
`https://bot.myjob.co.ua/` повертає 502 (це означає Nginx працює, але upstream немає).

---

## 🐳 Етап 7 — Запуск Docker контейнера

### 7.1. Build образу
```bash
cd /path/to/myjob
docker compose build bot
```

Очікуваний час: 1-3 хв (multi-stage build).

### 7.2. Запуск
```bash
docker compose up -d bot
```

### 7.3. Перевірити логи
```bash
docker compose logs bot --tail 50
# або follow:
docker compose logs bot -f
```

**Що очікувати в логах:**
```
INFO: Started server process [1]
INFO: Waiting for application startup.
INFO: Webhook set: https://bot.myjob.co.ua/telegram/webhook/...
INFO: Application startup complete.
INFO: Uvicorn running on http://0.0.0.0:8080
```

**Червоні прапори в логах:**
- `TelegramError: Unauthorized` → неправильний TELEGRAM_BOT_TOKEN
- `Failed to set webhook` → проблема з PUBLIC_URL або SSL
- `Permission denied` → проблема з Docker volumes
- `Cannot connect to host nginx:8080` → проблема з Docker network (Laravel-сервіс інакше називається?)

### 7.4. Health check
```bash
# З середини Docker network (через Laravel-контейнер)
docker compose exec app curl http://bot:8080/health
# Очікувано: {"status":"ok","bot_username":"myjob_in_bot"}

# Ззовні через Nginx
curl https://bot.myjob.co.ua/health
# Очікувано те саме
```

### ☑ Чекпойнт 7
Контейнер запущений, `/health` відповідає 200, у логах — `Webhook set`.

---

## 📡 Етап 8 — Telegram webhook verification

### 8.1. Перевірити webhook у Telegram
```bash
TOKEN=<твій TELEGRAM_BOT_TOKEN>
curl https://api.telegram.org/bot${TOKEN}/getWebhookInfo
```

**Очікуваний response:**
```json
{
  "ok": true,
  "result": {
    "url": "https://bot.myjob.co.ua/telegram/webhook/abc123...",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "max_connections": 40,
    "allowed_updates": ["message", "callback_query"]
  }
}
```

**Червоні прапори:**
- `"url": ""` → webhook не встановлено, перевірити логи бота на старті
- `"last_error_date": <timestamp>` + `"last_error_message": "..."` → Telegram не може достукатись до боту, причина в `last_error_message`

### ☑ Чекпойнт 8
Webhook встановлено, без errors.

---

## 🧪 Етап 9 — UAT smoke tests

### Test 1: `/start` (простий)
- Відкрити @myjob_in_bot у Telegram (зі свого особистого акаунта)
- Надіслати `/start`
- **Очікувано:** Бот відповідає привітанням українською, БЕЗ кнопок
- **Червоний прапор:** немає відповіді → перевірити `docker compose logs bot -f`, чи приходить update

### Test 2: `/health` ззовні
```bash
curl https://bot.myjob.co.ua/health
# Очікувано: {"status":"ok","bot_username":"myjob_in_bot"}
```

### Test 3: Laravel → Bot (sendMessage через tinker)
```bash
docker compose exec app php artisan tinker
```
У tinker:
```php
$notifier = app(\App\Services\TelegramNotifier::class);
$telegramId = <твій Telegram user ID — взяти з логів бота після /start>;
$result = $notifier->sendMessage($telegramId, "Тест з Laravel — " . now());
dd($result);
```
**Очікувано:** у tinker — `TelegramMessageResult{success: true, message_id: ...}`, у Telegram приходить повідомлення.

**Як знайти свій Telegram user ID:**
- У логах бота після `/start` має бути `from_user.id`
- Або через @userinfobot у Telegram

### Test 4: Auth flow E2E
1. На сайті myjob.co.ua (UAT environment): натиснути «Увійти через Telegram» (якщо такої кнопки немає — створити тестовий маршрут вручну, що викликає `TelegramAuthService::createSession()`)
2. Перейти за згенерованим deep link → відкривається бот
3. Бот просить поділитися контактом
4. Натиснути «📱 Поділитися номером»
5. **Очікувано:** Бот відповідає "✅ Вхід підтверджено. Поверніться на сайт." Сайт автоматично логінить (polling завершується).

**Якщо невідомий номер:** бот має сказати "⚠️ Цей номер не зареєстрований..."

### Test 5: Callback button (manual)
Найскладніший тест, бо callback handler на стороні Laravel ще не написаний (це Phase 2/3). Можна провалідувати тільки що **forward працює**:

У tinker:
```php
$notifier = app(\App\Services\TelegramNotifier::class);
$signer = app(\App\Services\Telegram\CallbackDataSigner::class);
$callbackData = $signer->sign('test', 'demo', 1, 'param');

$notifier->sendMessageWithKeyboard(
    $telegramId,
    "Тест callback",
    [[['text' => 'Click me', 'callback_data' => $callbackData]]]
);
```

У Telegram прийде повідомлення з кнопкою. Натиснути її. **Очікувано в логах:**
- Bot: `Received callback_query, forwarding to Laravel`
- Laravel: `TelegramCallbackRouter::dispatch called with action=test`
- (Laravel залогує "No handler found" — це нормально для Phase 1, бо специфічні handlers ще не зареєстровані)

### ☑ Чекпойнт 9
Усі 5 тестів пройдені (або failures зафіксовані з debug-інформацією).

---

## 🚨 Етап 10 — Якщо щось пішло не так

### Сценарій A: `/start` не відповідає
1. Перевірити логи: `docker compose logs bot -f`
2. Чи приходить update від Telegram?
3. Якщо ні → перевірити `getWebhookInfo` — чи правильний URL, чи нема last_error
4. Якщо так, але немає reply → читати traceback у логах

### Сценарій B: Laravel → Bot connection refused
1. Чи бот контейнер запущений? `docker compose ps`
2. Чи Laravel і Bot у тій самій Docker network? `docker network inspect <network>`
3. Чи правильна назва сервісу в `TELEGRAM_BOT_API_URL` (`bot:8080`)?
4. Тест зсередини Laravel-контейнера: `docker compose exec app curl -v http://bot:8080/health`

### Сценарій C: 401 Unauthorized від Bot
- Shared secrets не збігаються. Перевірити `BOT_API_TOKEN` у bot/.env == `TELEGRAM_BOT_API_TOKEN` у .env

### Сценарій D: Webhook last_error: "SSL handshake failed"
- Cloudflare SSL mode = Flexible замість Full (strict). Telegram потребує валідний HTTPS.
- Перевірити: `curl -vI https://bot.myjob.co.ua/health` — чи без warnings про SSL

### Сценарій E: Contact share → Laravel returns 401/403
- `LARAVEL_WEBHOOK_TOKEN` (bot) != `TELEGRAM_WEBHOOK_TOKEN` (Laravel)
- Або endpoint `/api/telegram/auth/contact` не закешований у route cache: `docker compose exec app php artisan route:clear`

---

## 📝 Фінальна верифікація

Заповнити після проходження:

```
☐ Etap 1 — Secrets generated and stored securely
☐ Etap 2 — DNS bot.myjob.co.ua resolving via Cloudflare
☐ Etap 3 — SSL cert valid for bot.myjob.co.ua
☐ Etap 4 — Code pulled to VPS
☐ Etap 5 — Both .env files configured, shared secrets matching
☐ Etap 6 — Nginx config active, returns 502 before container start
☐ Etap 7 — Docker container running, /health returns 200
☐ Etap 8 — Telegram webhook set, no errors
☐ Etap 9.1 — /start works ✓
☐ Etap 9.2 — /health works externally ✓
☐ Etap 9.3 — Laravel→Bot sendMessage works ✓
☐ Etap 9.4 — Auth flow E2E works ✓
☐ Etap 9.5 — Callback forwarding works ✓
```

**Якщо всі ☐ → ☑** — Phase 1B повністю задеплоєна та валідована. Готова до Phase 3.1.

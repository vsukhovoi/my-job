# MyJob Telegram Bot

FastAPI + python-telegram-bot v21 сервіс для [@myjob_in_bot](https://t.me/myjob_in_bot).

## Швидкий старт (локально)

```bash
cd bot
cp .env.example .env
# Заповніть .env
pip install ".[dev]"
uvicorn src.main:app --reload --port 8080
```

## Тести

```bash
cd bot
pytest -v
```

## Deploy (покроково)

1. **BotFather** — отримати `TELEGRAM_BOT_TOKEN`
2. **Генерувати secrets** (`openssl rand -hex 32` × кожен):
   - `BOT_API_TOKEN` — має збігатись з Laravel `TELEGRAM_BOT_API_TOKEN`
   - `LARAVEL_WEBHOOK_TOKEN` — має збігатись з Laravel `TELEGRAM_WEBHOOK_TOKEN`
   - `TELEGRAM_WEBHOOK_SECRET` — унікальний для цього бота
3. **DNS** — додати A-запис `bot.myjob.co.ua` → IP VPS, увімкнути Cloudflare proxy
4. **Nginx** — додати server block з `docker/nginx/bot.myjob.co.ua.conf` до aigent nginx
5. **Docker** — `docker compose up -d bot`
6. **Перевірити webhook**:
   ```
   curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo
   ```
7. **Smoke test** — написати `/start` боту в Telegram

## Env-змінні (синхронізація з Laravel)

| Bot               | Laravel                    | Значення     |
|-------------------|----------------------------|--------------|
| BOT_API_TOKEN     | TELEGRAM_BOT_API_TOKEN     | **однакові** |
| LARAVEL_WEBHOOK_TOKEN | TELEGRAM_WEBHOOK_TOKEN | **однакові** |
| LARAVEL_API_URL   | —                          | `http://nginx:8080` |

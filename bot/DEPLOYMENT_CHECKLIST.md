# Deployment Checklist — bot.myjob.co.ua

- [ ] DNS: `bot.myjob.co.ua` A → VPS IP, Cloudflare proxy увімкнено
- [ ] SSL: wildcard cert `*.myjob.co.ua` або окремий (Let's Encrypt)
- [ ] Nginx: server block `bot.myjob.co.ua.conf` додано до aigent nginx та перезавантажено
- [ ] `bot/.env` заповнено (усі 7 обов'язкових полів)
- [ ] Shared secrets узгоджені (порівняти з Laravel `.env`):
  - [ ] `BOT_API_TOKEN` == Laravel `TELEGRAM_BOT_API_TOKEN`
  - [ ] `LARAVEL_WEBHOOK_TOKEN` == Laravel `TELEGRAM_WEBHOOK_TOKEN`
- [ ] `docker compose up -d bot` виконано на VPS
- [ ] `docker compose ps bot` — статус healthy
- [ ] `curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo` — webhook URL правильний
- [ ] `/start` команда → привітальне повідомлення
- [ ] `/start auth_<test_token>` → кнопка "Поділитися номером"
- [ ] Тестовий callback з Laravel → видно в `docker compose logs bot`
- [ ] `curl https://bot.myjob.co.ua/health` → `{"status":"ok","bot_username":"myjob_in_bot"}`

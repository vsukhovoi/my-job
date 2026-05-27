from __future__ import annotations

import logging

from telegram import KeyboardButton, ReplyKeyboardMarkup

from src import auth_state, laravel_client
from src.telegram_client import get_bot

logger = logging.getLogger(__name__)

_WELCOME_TEXT = """Вітаю! Це бот платформи My Job.

Я надсилаю сповіщення про:
• нові вакансії за вашими підписками
• заявки на ваші вакансії
• запити на співбесіди та відповіді

Щоб увійти у свій акаунт через Telegram — натисніть кнопку на сайті myjob.co.ua, я вам допоможу."""

_AUTH_PROMPT = (
    "Для входу у акаунт поділіться, будь ласка, своїм номером телефону.\n"
    "Це безпечно — ми використаємо його тільки для прив'язки до існуючого профілю."
)


async def handle_start(chat_id: int, user_id: int, text: str) -> None:
    parts = text.split(maxsplit=1)
    payload = parts[1] if len(parts) == 2 else ""

    if payload.startswith("auth_"):
        token = payload[len("auth_"):]
        auth_state.store(user_id, token)
        logger.info("Auth flow started user_id=%s", user_id)

        reply_kb = ReplyKeyboardMarkup(
            [[KeyboardButton("📱 Поділитися номером", request_contact=True)]],
            resize_keyboard=True,
            one_time_keyboard=True,
        )
        await get_bot().send_message(
            chat_id=chat_id,
            text=_AUTH_PROMPT,
            reply_markup=reply_kb,
        )
        return

    if payload.startswith("link_"):
        link_token = payload[len("link_"):]
        logger.info("Account link flow user_id=%s", user_id)

        try:
            resp = await laravel_client.post(
                "/api/telegram/link",
                json={"link_token": link_token, "telegram_user_id": user_id},
            )
        except Exception as exc:
            logger.error("Account link: Laravel call failed user_id=%s error=%s", user_id, exc)
            await get_bot().send_message(
                chat_id=chat_id,
                text="❌ Сталася помилка. Спробуйте пізніше або зверніться у підтримку.",
            )
            return

        if resp.status_code == 200:
            await get_bot().send_message(
                chat_id=chat_id,
                text="✅ Telegram успішно прив'язано! Тепер ви отримуватимете сповіщення.",
            )
        elif resp.status_code == 404:
            await get_bot().send_message(
                chat_id=chat_id,
                text="❌ Посилання недійсне або застаріло. Спробуйте ще раз у профілі на сайті.",
            )
        else:
            logger.error("Account link: unexpected response %s user_id=%s", resp.status_code, user_id)
            await get_bot().send_message(
                chat_id=chat_id,
                text="❌ Сталася помилка. Спробуйте пізніше.",
            )
        return

    await get_bot().send_message(chat_id=chat_id, text=_WELCOME_TEXT)

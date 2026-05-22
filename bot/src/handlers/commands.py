from __future__ import annotations

import logging

from telegram import KeyboardButton, ReplyKeyboardMarkup

from src import auth_state
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
    if len(parts) == 2 and parts[1].startswith("auth_"):
        token = parts[1][len("auth_"):]
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

    await get_bot().send_message(chat_id=chat_id, text=_WELCOME_TEXT)

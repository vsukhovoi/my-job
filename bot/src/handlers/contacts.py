from __future__ import annotations

import logging

from telegram import ReplyKeyboardRemove

from src import auth_state, laravel_client
from src.telegram_client import get_bot

logger = logging.getLogger(__name__)


async def handle_contact_share(
    chat_id: int,
    user_id: int,
    phone: str,
    first_name: str | None,
    last_name: str | None,
    username: str | None,
) -> None:
    if not phone.startswith("+"):
        phone = f"+{phone}"

    token = auth_state.pop(user_id)

    if token is None:
        logger.warning("Contact share without pending auth user_id=%s", user_id)
        await get_bot().send_message(
            chat_id=chat_id,
            text="⚠️ Немає активного запиту авторизації. Натисніть кнопку «Увійти через Telegram» на сайті.",
            reply_markup=ReplyKeyboardRemove(),
        )
        return

    payload = {
        "auth_token":       token,
        "telegram_user_id": user_id,
        "phone":            phone,
        "first_name":       first_name or None,
        "last_name":        last_name or None,
        "username":         username or None,
    }

    try:
        resp = await laravel_client.post("/api/telegram/auth/contact", json=payload)
    except Exception as exc:
        logger.error("Contact share: Laravel call failed user_id=%s error=%s", user_id, exc)
        await get_bot().send_message(
            chat_id=chat_id,
            text="❌ Сталася помилка. Спробуйте пізніше або зверніться у підтримку.",
            reply_markup=ReplyKeyboardRemove(),
        )
        return

    if resp.status_code == 200 and resp.json().get("matched"):
        reply = "✅ Вхід підтверджено. Поверніться на сайт."
    elif resp.status_code == 200 and not resp.json().get("matched"):
        reply = "⚠️ Цей номер не зареєстрований на My Job. Спочатку зареєструйтеся на сайті."
    else:
        logger.error("Contact share: unexpected response %s user_id=%s", resp.status_code, user_id)
        reply = "❌ Сталася помилка. Спробуйте пізніше або зверніться у підтримку."

    await get_bot().send_message(
        chat_id=chat_id,
        text=reply,
        reply_markup=ReplyKeyboardRemove(),
    )

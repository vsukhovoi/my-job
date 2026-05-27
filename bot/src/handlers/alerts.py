from __future__ import annotations

import logging

from telegram import InlineKeyboardButton, InlineKeyboardMarkup

from src import laravel_client
from src.telegram_client import get_bot

logger = logging.getLogger(__name__)

_ALERTS_TITLE = "🔔 <b>Сповіщення про вакансії</b>\n\nОберіть категорії для отримання сповіщень:"


def _build_keyboard(categories: list[dict]) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup([
        [InlineKeyboardButton(
            text=("✅ " if cat["subscribed"] else "") + cat["name"],
            callback_data=f"alert_toggle:{cat['id']}",
        )]
        for cat in categories
    ])


async def handle_alerts(chat_id: int, user_id: int) -> None:
    try:
        resp = await laravel_client.get(f"/api/telegram/alerts?telegram_user_id={user_id}")
    except Exception as exc:
        logger.error("Alerts: Laravel call failed user_id=%s error=%s", user_id, exc)
        await get_bot().send_message(chat_id=chat_id, text="❌ Сталася помилка. Спробуйте пізніше.")
        return

    if resp.status_code != 200:
        await get_bot().send_message(chat_id=chat_id, text="❌ Сталася помилка. Спробуйте пізніше.")
        return

    categories = resp.json().get("categories", [])

    if not categories:
        await get_bot().send_message(chat_id=chat_id, text="ℹ️ Категорії поки відсутні.")
        return

    await get_bot().send_message(
        chat_id=chat_id,
        text=_ALERTS_TITLE,
        parse_mode="HTML",
        reply_markup=_build_keyboard(categories),
    )


async def handle_alert_toggle(
    chat_id: int,
    user_id: int,
    query_id: str,
    message_id: int | None,
    category_id: int,
) -> None:
    try:
        resp = await laravel_client.post(
            "/api/telegram/alerts/toggle",
            json={"telegram_user_id": user_id, "category_id": category_id},
        )
    except Exception as exc:
        logger.error("Alert toggle: Laravel call failed user_id=%s error=%s", user_id, exc)
        await get_bot().answer_callback_query(callback_query_id=query_id, text="❌ Помилка. Спробуйте пізніше.")
        return

    if resp.status_code == 404:
        await get_bot().answer_callback_query(callback_query_id=query_id, text="❌ Категорію не знайдено.")
        return

    if resp.status_code != 200:
        await get_bot().answer_callback_query(callback_query_id=query_id, text="❌ Помилка. Спробуйте пізніше.")
        return

    data = resp.json()
    notice = ("✅ Підписано: " if data["subscribed"] else "❌ Відписано: ") + data["category"]

    await get_bot().answer_callback_query(callback_query_id=query_id, text=notice)

    if message_id:
        try:
            await get_bot().edit_message_text(
                chat_id=chat_id,
                message_id=message_id,
                text=_ALERTS_TITLE,
                parse_mode="HTML",
                reply_markup=_build_keyboard(data["categories"]),
            )
        except Exception as exc:
            logger.warning("Alert toggle: edit_message failed user_id=%s error=%s", user_id, exc)

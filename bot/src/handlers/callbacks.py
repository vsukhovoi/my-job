from __future__ import annotations

import logging

from src import laravel_client

logger = logging.getLogger(__name__)


async def handle_callback(
    user_id: int,
    message_id: int | None,
    query_id: str,
    data: str,
) -> None:
    payload = {
        "telegram_user_id":  user_id,
        "callback_data":     data,
        "message_id":        message_id,
        "callback_query_id": query_id,
    }

    try:
        resp = await laravel_client.post("/api/telegram/webhook/callback", json=payload)
        logger.info("Callback forwarded user_id=%s status=%s", user_id, resp.status_code)
    except Exception as exc:
        logger.error("Callback forward failed user_id=%s error=%s", user_id, exc)

from __future__ import annotations

import logging
from contextlib import asynccontextmanager
from typing import Any

from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import JSONResponse

from src.api.routes import router
from src.config import settings
from src.handlers.callbacks import handle_callback
from src.handlers.commands import handle_start
from src.handlers.contacts import handle_contact_share
from src.telegram_client import get_bot
from src.utils.logging import configure_logging

configure_logging()
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):  # type: ignore[type-arg]
    bot = get_bot()
    webhook_url = f"{settings.PUBLIC_URL}/telegram/webhook/{settings.TELEGRAM_WEBHOOK_SECRET}"
    await bot.set_webhook(
        url=webhook_url,
        allowed_updates=["message", "callback_query"],
    )
    logger.info("Webhook set: %s", webhook_url)
    yield
    if settings.ENV == "local":
        await bot.delete_webhook()
        logger.info("Webhook deleted (local env)")


app = FastAPI(title="MyJob Bot", lifespan=lifespan)
app.include_router(router)


@app.post("/telegram/webhook/{secret_path}")
async def telegram_webhook(secret_path: str, request: Request) -> dict[str, Any]:
    if secret_path != settings.TELEGRAM_WEBHOOK_SECRET:
        raise HTTPException(status_code=404)

    data = await request.json()

    try:
        if "message" in data:
            msg = data["message"]
            user = msg.get("from") or {}
            user_id = user.get("id") or msg["chat"]["id"]
            chat_id = msg["chat"]["id"]
            text = msg.get("text", "")

            if text.startswith("/start"):
                await handle_start(chat_id=chat_id, user_id=user_id, text=text)
            elif "contact" in msg:
                contact = msg["contact"]
                await handle_contact_share(
                    chat_id=chat_id,
                    user_id=user_id,
                    phone=contact.get("phone_number", ""),
                    first_name=contact.get("first_name"),
                    last_name=contact.get("last_name"),
                    username=user.get("username"),
                )
        elif "callback_query" in data:
            cb = data["callback_query"]
            await handle_callback(
                user_id=cb["from"]["id"],
                message_id=(cb.get("message") or {}).get("message_id"),
                query_id=cb["id"],
                data=cb.get("data", ""),
            )
    except Exception as exc:
        logger.warning("Error processing update: %s", exc)

    return {"ok": True}


@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception) -> JSONResponse:
    logger.error("Unhandled exception: %s", exc, exc_info=True)
    return JSONResponse(status_code=500, content={"detail": "Internal server error"})

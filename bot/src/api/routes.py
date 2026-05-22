from __future__ import annotations

import logging
import time

from fastapi import APIRouter, Depends
from telegram import InlineKeyboardButton, InlineKeyboardMarkup
from telegram.error import BadRequest, Forbidden, TelegramError

from src.api.deps import verify_bearer_token
from src.api.schemas import (
    AnswerCallbackRequest,
    AnswerCallbackResponse,
    EditMessageRequest,
    EditMessageResponse,
    HealthResponse,
    SendMessageRequest,
    SendMessageResponse,
    SendMessageWithKeyboardRequest,
)
from src.telegram_client import get_bot

logger = logging.getLogger(__name__)
router = APIRouter()

_bot_info_cache: dict[str, str] = {}


@router.get("/health", response_model=HealthResponse)
async def health() -> HealthResponse:
    if "username" not in _bot_info_cache:
        me = await get_bot().get_me()
        _bot_info_cache["username"] = me.username or "unknown"
    return HealthResponse(status="ok", bot_username=_bot_info_cache["username"])


@router.post(
    "/send-message",
    response_model=SendMessageResponse,
    dependencies=[Depends(verify_bearer_token)],
)
async def send_message(body: SendMessageRequest) -> SendMessageResponse:
    t0 = time.monotonic()
    try:
        msg = await get_bot().send_message(
            chat_id=body.chat_id,
            text=body.text,
            parse_mode=body.parse_mode,
        )
        elapsed = time.monotonic() - t0
        logger.info("send_message chat_id=%s msg_id=%s elapsed=%.3fs", body.chat_id, msg.message_id, elapsed)
        return SendMessageResponse(success=True, message_id=msg.message_id)
    except Forbidden as exc:
        logger.warning("send_message chat_id=%s forbidden: %s", body.chat_id, exc)
        return SendMessageResponse(success=False, error="User blocked the bot")
    except (BadRequest, TelegramError) as exc:
        logger.error("send_message chat_id=%s error: %s", body.chat_id, exc)
        return SendMessageResponse(success=False, error=str(exc))


@router.post(
    "/send-message-with-keyboard",
    response_model=SendMessageResponse,
    dependencies=[Depends(verify_bearer_token)],
)
async def send_message_with_keyboard(body: SendMessageWithKeyboardRequest) -> SendMessageResponse:
    t0 = time.monotonic()
    markup = InlineKeyboardMarkup([
        [
            InlineKeyboardButton(
                text=btn.text,
                callback_data=btn.callback_data,
                url=btn.url,
            )
            for btn in row
        ]
        for row in body.inline_keyboard.inline_keyboard
    ])
    try:
        msg = await get_bot().send_message(
            chat_id=body.chat_id,
            text=body.text,
            parse_mode=body.parse_mode,
            reply_markup=markup,
        )
        elapsed = time.monotonic() - t0
        logger.info(
            "send_message_with_keyboard chat_id=%s msg_id=%s elapsed=%.3fs",
            body.chat_id, msg.message_id, elapsed,
        )
        return SendMessageResponse(success=True, message_id=msg.message_id)
    except Forbidden as exc:
        logger.warning("send_message_with_keyboard chat_id=%s forbidden: %s", body.chat_id, exc)
        return SendMessageResponse(success=False, error="User blocked the bot")
    except (BadRequest, TelegramError) as exc:
        logger.error("send_message_with_keyboard chat_id=%s error: %s", body.chat_id, exc)
        return SendMessageResponse(success=False, error=str(exc))


@router.post(
    "/edit-message",
    response_model=EditMessageResponse,
    dependencies=[Depends(verify_bearer_token)],
)
async def edit_message(body: EditMessageRequest) -> EditMessageResponse:
    t0 = time.monotonic()
    markup = None
    if body.inline_keyboard:
        markup = InlineKeyboardMarkup([
            [
                InlineKeyboardButton(
                    text=btn.text,
                    callback_data=btn.callback_data,
                    url=btn.url,
                )
                for btn in row
            ]
            for row in body.inline_keyboard.inline_keyboard
        ])
    try:
        await get_bot().edit_message_text(
            text=body.text,
            chat_id=body.chat_id,
            message_id=body.message_id,
            parse_mode=body.parse_mode,
            reply_markup=markup,
        )
        elapsed = time.monotonic() - t0
        logger.info("edit_message chat_id=%s msg_id=%s elapsed=%.3fs", body.chat_id, body.message_id, elapsed)
        return EditMessageResponse(success=True)
    except BadRequest as exc:
        # Gracefully handle "Message is not modified" and deleted messages
        logger.info("edit_message chat_id=%s bad_request: %s", body.chat_id, exc)
        return EditMessageResponse(success=False, error=str(exc))
    except TelegramError as exc:
        logger.error("edit_message chat_id=%s error: %s", body.chat_id, exc)
        return EditMessageResponse(success=False, error=str(exc))


@router.post(
    "/answer-callback",
    response_model=AnswerCallbackResponse,
    dependencies=[Depends(verify_bearer_token)],
)
async def answer_callback(body: AnswerCallbackRequest) -> AnswerCallbackResponse:
    t0 = time.monotonic()
    try:
        await get_bot().answer_callback_query(
            callback_query_id=body.callback_query_id,
            text=body.text,
            show_alert=body.show_alert,
        )
        elapsed = time.monotonic() - t0
        logger.info("answer_callback id=%s elapsed=%.3fs", body.callback_query_id, elapsed)
        return AnswerCallbackResponse(success=True)
    except TelegramError as exc:
        logger.error("answer_callback id=%s error: %s", body.callback_query_id, exc)
        return AnswerCallbackResponse(success=False, error=str(exc))

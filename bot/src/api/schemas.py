from __future__ import annotations

from pydantic import BaseModel


# ── Inline keyboard ────────────────────────────────────────────────────────

class InlineButton(BaseModel):
    text: str
    callback_data: str | None = None
    url: str | None = None


class InlineKeyboard(BaseModel):
    inline_keyboard: list[list[InlineButton]]


# ── Request schemas ────────────────────────────────────────────────────────

class SendMessageRequest(BaseModel):
    chat_id: int
    text: str
    parse_mode: str | None = "HTML"


class SendMessageWithKeyboardRequest(BaseModel):
    chat_id: int
    text: str
    parse_mode: str | None = "HTML"
    inline_keyboard: InlineKeyboard


class EditMessageRequest(BaseModel):
    chat_id: int
    message_id: int
    text: str
    parse_mode: str | None = "HTML"
    inline_keyboard: InlineKeyboard | None = None


class AnswerCallbackRequest(BaseModel):
    callback_query_id: str
    text: str | None = None
    show_alert: bool = False


# ── Response schemas ───────────────────────────────────────────────────────

class SendMessageResponse(BaseModel):
    success: bool
    message_id: int | None = None
    error: str | None = None


class EditMessageResponse(BaseModel):
    success: bool
    error: str | None = None


class AnswerCallbackResponse(BaseModel):
    success: bool
    error: str | None = None


class HealthResponse(BaseModel):
    status: str
    bot_username: str

"""Singleton wrapper around telegram.Bot.

Use set_bot() in tests to inject a mock; get_bot() everywhere else.
"""
from __future__ import annotations

from telegram import Bot

from src.config import settings

_bot: Bot | None = None


def get_bot() -> Bot:
    global _bot
    if _bot is None:
        _bot = Bot(token=settings.TELEGRAM_BOT_TOKEN)
    return _bot


def set_bot(bot: Bot) -> None:
    global _bot
    _bot = bot


def reset_bot() -> None:
    global _bot
    _bot = None

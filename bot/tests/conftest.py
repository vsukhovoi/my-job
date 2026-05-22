"""Set env vars BEFORE any src imports so pydantic-settings reads them."""
from __future__ import annotations

import os

os.environ.setdefault("TELEGRAM_BOT_TOKEN", "test-bot-token")
os.environ.setdefault("BOT_API_TOKEN", "test-bot-api-token")
os.environ.setdefault("LARAVEL_WEBHOOK_TOKEN", "test-webhook-token")
os.environ.setdefault("TELEGRAM_WEBHOOK_SECRET", "testsecret")
os.environ.setdefault("LARAVEL_API_URL", "http://nginx:8080")
os.environ.setdefault("PUBLIC_URL", "https://bot.myjob.co.ua")

from unittest.mock import AsyncMock, MagicMock

import pytest
from starlette.testclient import TestClient

import src.telegram_client as tc
from src import auth_state
from src.main import app

BEARER = "Bearer test-bot-api-token"
WEBHOOK_PATH = "/telegram/webhook/testsecret"


@pytest.fixture(autouse=True)
def clear_auth_state():
    auth_state.clear()
    yield
    auth_state.clear()


@pytest.fixture
def mock_bot():
    bot = AsyncMock()
    bot.get_me = AsyncMock(return_value=MagicMock(username="myjob_in_bot"))
    bot.set_webhook = AsyncMock(return_value=True)
    bot.delete_webhook = AsyncMock(return_value=True)
    bot.send_message = AsyncMock()
    bot.edit_message_text = AsyncMock()
    bot.answer_callback_query = AsyncMock()
    return bot


@pytest.fixture
def client(mock_bot):
    tc._bot = mock_bot
    with TestClient(app, raise_server_exceptions=False) as c:
        yield c
    tc.reset_bot()

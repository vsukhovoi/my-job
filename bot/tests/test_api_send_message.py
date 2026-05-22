from __future__ import annotations

from unittest.mock import MagicMock

import pytest
from telegram.error import Forbidden, TelegramError


class TestSendMessage:
    def test_success(self, client, mock_bot):
        msg = MagicMock()
        msg.message_id = 42
        mock_bot.send_message.return_value = msg

        resp = client.post(
            "/send-message",
            json={"chat_id": 123, "text": "Привіт!", "parse_mode": "HTML"},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        body = resp.json()
        assert body["success"] is True
        assert body["message_id"] == 42
        mock_bot.send_message.assert_called_once()

    def test_returns_401_without_bearer(self, client, mock_bot):
        resp = client.post("/send-message", json={"chat_id": 1, "text": "x"})
        assert resp.status_code in (401, 422)

    def test_handles_forbidden(self, client, mock_bot):
        mock_bot.send_message.side_effect = Forbidden("Forbidden: bot was blocked by the user")

        resp = client.post(
            "/send-message",
            json={"chat_id": 999, "text": "hi"},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        body = resp.json()
        assert body["success"] is False
        assert "blocked" in body["error"].lower()

    def test_handles_telegram_error(self, client, mock_bot):
        mock_bot.send_message.side_effect = TelegramError("Network error")

        resp = client.post(
            "/send-message",
            json={"chat_id": 1, "text": "hi"},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        assert resp.json()["success"] is False

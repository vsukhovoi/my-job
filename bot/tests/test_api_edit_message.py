from __future__ import annotations

from unittest.mock import MagicMock

from telegram.error import BadRequest


class TestEditMessage:
    def test_successful_edit(self, client, mock_bot):
        mock_bot.edit_message_text.return_value = MagicMock()

        resp = client.post(
            "/edit-message",
            json={"chat_id": 123, "message_id": 10, "text": "Оновлено", "parse_mode": "HTML"},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        assert resp.json()["success"] is True
        mock_bot.edit_message_text.assert_called_once()

    def test_graceful_message_not_modified(self, client, mock_bot):
        mock_bot.edit_message_text.side_effect = BadRequest("Message is not modified")

        resp = client.post(
            "/edit-message",
            json={"chat_id": 123, "message_id": 10, "text": "Same text"},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        body = resp.json()
        assert body["success"] is False
        assert "not modified" in body["error"].lower()

    def test_returns_401_without_bearer(self, client, mock_bot):
        resp = client.post("/edit-message", json={})
        assert resp.status_code in (401, 422)

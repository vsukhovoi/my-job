from __future__ import annotations

from unittest.mock import MagicMock


class TestSendMessageWithKeyboard:
    def test_keyboard_forwarded_to_telegram(self, client, mock_bot):
        msg = MagicMock()
        msg.message_id = 99
        mock_bot.send_message.return_value = msg

        payload = {
            "chat_id": 456,
            "text": "Оберіть дію:",
            "parse_mode": "HTML",
            "inline_keyboard": {
                "inline_keyboard": [
                    [{"text": "Переглянути", "callback_data": "view:application:1:_:sig"}],
                    [{"text": "Відхилити", "callback_data": "reject:application:1:_:sig"}],
                ]
            },
        }

        resp = client.post(
            "/send-message-with-keyboard",
            json=payload,
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        body = resp.json()
        assert body["success"] is True
        assert body["message_id"] == 99

        call_kwargs = mock_bot.send_message.call_args
        _, kwargs = call_kwargs
        assert kwargs["chat_id"] == 456
        assert kwargs["reply_markup"] is not None

    def test_returns_401_without_bearer(self, client, mock_bot):
        resp = client.post("/send-message-with-keyboard", json={})
        assert resp.status_code in (401, 422)

from __future__ import annotations

from src import auth_state


def _start_update(text: str, user_id: int = 111222) -> dict:
    return {
        "update_id": 1,
        "message": {
            "message_id": 100,
            "from": {"id": user_id, "is_bot": False, "first_name": "Тест", "username": "testuser"},
            "chat": {"id": user_id, "type": "private"},
            "date": 1700000000,
            "text": text,
        },
    }


class TestStartCommand:
    def test_plain_start_sends_welcome(self, client, mock_bot):
        resp = client.post("/telegram/webhook/testsecret", json=_start_update("/start"))
        assert resp.status_code == 200
        mock_bot.send_message.assert_called_once()
        call_kwargs = mock_bot.send_message.call_args[1]
        assert "My Job" in call_kwargs["text"]

    def test_auth_deep_link_requests_contact_share(self, client, mock_bot):
        user_id = 333444
        resp = client.post(
            "/telegram/webhook/testsecret",
            json=_start_update("/start auth_abc123token", user_id=user_id),
        )
        assert resp.status_code == 200
        mock_bot.send_message.assert_called_once()
        call_kwargs = mock_bot.send_message.call_args[1]
        # Should ask for contact share
        assert "номером" in call_kwargs["text"]
        assert call_kwargs["reply_markup"] is not None
        # Token should be stored in auth_state for the next contact share step
        # (not popped yet — that happens when the contact arrives)
        stored = auth_state.pop(user_id)
        assert stored == "abc123token"

    def test_wrong_secret_returns_404(self, client, mock_bot):
        resp = client.post(
            "/telegram/webhook/wrongsecret",
            json=_start_update("/start"),
        )
        assert resp.status_code == 404

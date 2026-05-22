from __future__ import annotations

from src import auth_state


def _contact_update(user_id: int, phone: str) -> dict:
    return {
        "update_id": 2,
        "message": {
            "message_id": 200,
            "from": {"id": user_id, "is_bot": False, "first_name": "Іван", "username": "ivan"},
            "chat": {"id": user_id, "type": "private"},
            "date": 1700000000,
            "contact": {
                "phone_number": phone,
                "first_name": "Іван",
                "user_id": user_id,
            },
        },
    }


class TestContactShare:
    def test_full_auth_flow_matched(self, client, mock_bot, httpx_mock):
        user_id = 555666
        auth_state.store(user_id, "valid-token")

        httpx_mock.add_response(
            url="http://nginx:8080/api/telegram/auth/contact",
            json={"matched": True, "user_id": 42},
            status_code=200,
        )

        resp = client.post(
            "/telegram/webhook/testsecret",
            json=_contact_update(user_id, "+380501234567"),
        )

        assert resp.status_code == 200
        mock_bot.send_message.assert_called_once()
        sent_text = mock_bot.send_message.call_args[1]["text"]
        assert "підтверджено" in sent_text

    def test_full_auth_flow_unmatched(self, client, mock_bot, httpx_mock):
        user_id = 777888
        auth_state.store(user_id, "unmatched-token")

        httpx_mock.add_response(
            url="http://nginx:8080/api/telegram/auth/contact",
            json={"matched": False},
            status_code=200,
        )

        resp = client.post(
            "/telegram/webhook/testsecret",
            json=_contact_update(user_id, "+380501111111"),
        )

        assert resp.status_code == 200
        sent_text = mock_bot.send_message.call_args[1]["text"]
        assert "не зареєстрований" in sent_text

    def test_contact_without_pending_token(self, client, mock_bot):
        resp = client.post(
            "/telegram/webhook/testsecret",
            json=_contact_update(999000, "+380509999999"),
        )
        assert resp.status_code == 200
        mock_bot.send_message.assert_called_once()
        sent_text = mock_bot.send_message.call_args[1]["text"]
        assert "запиту" in sent_text

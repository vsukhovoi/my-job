from __future__ import annotations


def _callback_update(user_id: int = 123456, data: str = "action:type:1:_:sig") -> dict:
    return {
        "update_id": 3,
        "callback_query": {
            "id": "cq_abc",
            "from": {"id": user_id, "is_bot": False, "first_name": "Test"},
            "message": {
                "message_id": 300,
                "chat": {"id": user_id, "type": "private"},
                "date": 1700000000,
                "text": "повідомлення",
            },
            "chat_instance": "xyz",
            "data": data,
        },
    }


class TestCallbackForwarding:
    def test_callback_forwarded_with_correct_header(self, client, mock_bot, httpx_mock):
        httpx_mock.add_response(
            url="http://nginx:8080/api/telegram/webhook/callback",
            json={"ok": True},
            status_code=200,
        )

        resp = client.post(
            "/telegram/webhook/testsecret",
            json=_callback_update(user_id=123456, data="view:application:7:_:hmacval"),
        )

        assert resp.status_code == 200

        sent = httpx_mock.get_requests()
        assert len(sent) == 1
        request = sent[0]
        assert request.url.path == "/api/telegram/webhook/callback"
        assert request.headers.get("x-telegram-webhook-token") == "test-webhook-token"

        import json
        body = json.loads(request.content)
        assert body["telegram_user_id"] == 123456
        assert body["callback_data"] == "view:application:7:_:hmacval"
        assert body["callback_query_id"] == "cq_abc"

    def test_bot_does_not_parse_callback_data(self, client, mock_bot, httpx_mock):
        """The bot must forward raw callback_data without modification."""
        raw_data = "reject:vacancy:42:extra:HMACsignatureXYZ"

        httpx_mock.add_response(
            url="http://nginx:8080/api/telegram/webhook/callback",
            json={"ok": True},
            status_code=200,
        )

        client.post(
            "/telegram/webhook/testsecret",
            json=_callback_update(data=raw_data),
        )

        import json
        body = json.loads(httpx_mock.get_requests()[0].content)
        assert body["callback_data"] == raw_data

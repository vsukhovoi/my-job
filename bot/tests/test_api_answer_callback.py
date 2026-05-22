from __future__ import annotations


class TestAnswerCallback:
    def test_answer_without_alert(self, client, mock_bot):
        mock_bot.answer_callback_query.return_value = True

        resp = client.post(
            "/answer-callback",
            json={"callback_query_id": "cq123", "text": "Виконано", "show_alert": False},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        assert resp.json()["success"] is True
        mock_bot.answer_callback_query.assert_called_once_with(
            callback_query_id="cq123",
            text="Виконано",
            show_alert=False,
        )

    def test_answer_with_alert(self, client, mock_bot):
        mock_bot.answer_callback_query.return_value = True

        resp = client.post(
            "/answer-callback",
            json={"callback_query_id": "cq456", "text": "Увага!", "show_alert": True},
            headers={"Authorization": "Bearer test-bot-api-token"},
        )

        assert resp.status_code == 200
        assert resp.json()["success"] is True
        _, kwargs = mock_bot.answer_callback_query.call_args
        assert kwargs["show_alert"] is True

    def test_returns_401_without_bearer(self, client, mock_bot):
        resp = client.post("/answer-callback", json={"callback_query_id": "x"})
        assert resp.status_code in (401, 422)

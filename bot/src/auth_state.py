"""In-memory auth token store for the /start auth flow.

Maps telegram_user_id → (auth_token, expiry_timestamp).
Single-worker only; for multi-worker deployments switch to Redis.
"""
from __future__ import annotations

import time

_pending: dict[int, tuple[str, float]] = {}
TTL = 300  # 5 minutes


def store(user_id: int, token: str) -> None:
    _pending[user_id] = (token, time.monotonic() + TTL)


def pop(user_id: int) -> str | None:
    entry = _pending.pop(user_id, None)
    if entry is None:
        return None
    token, expiry = entry
    if time.monotonic() > expiry:
        return None
    return token


def clear() -> None:
    _pending.clear()

"""Thin httpx wrapper for outgoing calls to Laravel."""
from __future__ import annotations

import logging

import httpx

from src.config import settings

logger = logging.getLogger(__name__)


async def post(path: str, json: dict) -> httpx.Response:
    """POST to Laravel and return the response.

    Raises httpx.HTTPError on network failure.
    """
    url = f"{settings.LARAVEL_API_URL}{path}"
    headers = {"X-Telegram-Webhook-Token": settings.LARAVEL_WEBHOOK_TOKEN}
    logger.debug("Laravel POST %s payload=%s", path, list(json.keys()))
    async with httpx.AsyncClient(timeout=10.0) as client:
        return await client.post(url, json=json, headers=headers)

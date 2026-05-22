from __future__ import annotations

from fastapi import Header, HTTPException

from src.config import settings


async def verify_bearer_token(authorization: str = Header(...)) -> None:
    expected = f"Bearer {settings.BOT_API_TOKEN}"
    if authorization != expected:
        raise HTTPException(status_code=401, detail="Invalid bearer token")

from __future__ import annotations

from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    TELEGRAM_BOT_TOKEN: str
    TELEGRAM_BOT_USERNAME: str = "myjob_in_bot"

    BOT_API_TOKEN: str

    LARAVEL_API_URL: str = "http://nginx:8080"
    LARAVEL_WEBHOOK_TOKEN: str

    TELEGRAM_WEBHOOK_SECRET: str

    PUBLIC_URL: str = "https://bot.myjob.co.ua"

    LOG_LEVEL: str = "INFO"
    ENV: Literal["local", "production"] = "production"


settings = Settings()

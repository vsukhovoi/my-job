<?php

declare(strict_types=1);

return [
    'token'        => env('TELEGRAM_TOKEN', ''),
    'webhook_url'  => env('TELEGRAM_WEBHOOK_URL', ''),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),
    'error_chat_id' => env('TELEGRAM_ERROR_CHAT_ID', ''),
];

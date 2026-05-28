<?php

declare(strict_types=1);

use App\Telegram\Callbacks\AlertToggleCallback;
use App\Telegram\Callbacks\VacancyActionCallback;
use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\ContactAuthHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

Route::post('/telegram/webhook', function (Nutgram $bot): void {
    $bot->setRunningMode(Webhook::class);
    $bot->onCommand('start', StartCommand::class);
    $bot->onCommand('start {payload}', StartCommand::class);
    $bot->onCommand('alerts', AlertsCommand::class);
    $bot->onCallbackQueryData('alert_toggle:[0-9]+', AlertToggleCallback::class);
    $bot->onCallbackQueryData('vac:[a-z]+:[0-9]+(:[0-9]+)?', VacancyActionCallback::class);
    $bot->onContact(ContactAuthHandler::class);

    try {
        $bot->run();
    } catch (\Throwable $e) {
        Log::warning('Telegram webhook error: ' . $e->getMessage());
    }
})->middleware('throttle:30,1');

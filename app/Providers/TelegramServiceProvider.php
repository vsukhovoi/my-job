<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Telegram\CallbackDataSigner;
use App\Services\Telegram\Handlers\InviteToInterviewHandler;
use App\Services\Telegram\Handlers\RejectApplicationHandler;
use App\Services\Telegram\TelegramCallbackRouter;
use App\Services\TelegramNotifier;
use App\Services\TelegramService;
use App\Telegram\Commands\StartCommand;
use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('telegram.php'), 'telegram');

        $this->app->singleton(Nutgram::class, function (): Nutgram {
            return new Nutgram((string) config('telegram.token'));
        });

        $this->app->singleton(TelegramService::class, function ($app): TelegramService {
            return new TelegramService($app->make(Nutgram::class));
        });

        $this->app->singleton(TelegramCallbackRouter::class, function ($app): TelegramCallbackRouter {
            return new TelegramCallbackRouter(
                signer:   $app->make(CallbackDataSigner::class),
                notifier: $app->make(TelegramNotifier::class),
                handlers: [
                    $app->make(RejectApplicationHandler::class),
                    $app->make(InviteToInterviewHandler::class),
                ],
            );
        });
    }

    public function boot(): void
    {
        //
    }
}

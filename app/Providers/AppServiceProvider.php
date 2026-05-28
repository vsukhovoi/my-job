<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;
use App\Observers\CandidateProfileObserver;
use App\Observers\VacancyObserver;
use App\Policies\ResumePolicy;
use App\Policies\VacancyPolicy;
use App\Events\ApplicationStatusChanged;
use App\Events\InterviewRequestSent;
use App\Events\InterviewResponseSubmitted;
use App\Events\InvoicePaid;
use App\Events\VacancyExtended;
use App\Listeners\ActivateOrderOnInvoicePaid;
use App\Listeners\BroadcastToLivewire;
use App\Listeners\NotifyApplicationStatusChanged;
use App\Listeners\NotifyEmployerOfExtension;
use App\Listeners\NotifyInterviewRequestSent;
use App\Listeners\NotifyInterviewResponseSubmitted;
use App\Listeners\SendStatusNotification;
use App\Notifications\Channels\TelegramChannel;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Carbon::setLocale('uk');

        $this->bootTelegramRateLimiters();

        Notification::extend('telegram', fn ($app) => $app->make(TelegramChannel::class));

        Event::listen(VacancyExtended::class, NotifyEmployerOfExtension::class);
        Event::listen(ApplicationStatusChanged::class, SendStatusNotification::class);
        Event::listen(ApplicationStatusChanged::class, BroadcastToLivewire::class);
        Event::listen(ApplicationStatusChanged::class, NotifyApplicationStatusChanged::class);
        Event::listen(InterviewRequestSent::class, NotifyInterviewRequestSent::class);
        Event::listen(InterviewResponseSubmitted::class, NotifyInterviewResponseSubmitted::class);
        Event::listen(InvoicePaid::class, ActivateOrderOnInvoicePaid::class);
        Event::listen(SocialiteWasCalled::class, AppleExtendSocialite::class);

        Gate::policy(Resume::class, ResumePolicy::class);
        Gate::policy(Vacancy::class, VacancyPolicy::class);

        User::observe(CandidateProfileObserver::class);
        Vacancy::observe(VacancyObserver::class);
    }

    private function bootTelegramRateLimiters(): void
    {
        // TTL сесії = 5 хв, polling = 3 сек → 100 запитів за цикл + 10 буфер = 110 per 5 min
        RateLimiter::for('telegram-auth-status', fn (Request $request) =>
            Limit::perMinutes(5, 110)
        );

        RateLimiter::for('telegram-auth-init', fn (Request $request) =>
            Limit::perMinute(20)
        );

        RateLimiter::for('telegram-auth-contact', fn (Request $request) =>
            Limit::perMinute(60)
        );

        RateLimiter::for('telegram-callback', fn (Request $request) =>
            Limit::perMinute(60)
        );

        RateLimiter::for('telegram-link', fn (Request $request) =>
            Limit::perMinute(30)
        );

        RateLimiter::for('telegram-alerts', fn (Request $request) =>
            Limit::perMinute(60)
        );

        RateLimiter::for('telegram-alerts-toggle', fn (Request $request) =>
            Limit::perMinute(60)
        );

        RateLimiter::for('telegram-webhook', fn (Request $request) =>
            Limit::perMinute(30)
        );
    }
}

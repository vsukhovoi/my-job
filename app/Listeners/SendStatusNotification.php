<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApplicationStatusChanged;
use App\Notifications\ApplicationStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendStatusNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(ApplicationStatusChanged $event): void
    {
        $seeker   = $event->application->user;
        $employer = $event->application->vacancy->company->user;

        Notification::send(
            [$seeker, $employer],
            new ApplicationStatusChangedNotification($event),
        );
    }
}

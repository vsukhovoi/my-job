<?php

declare(strict_types=1);

namespace App\Services\Telegram\UrlGenerators;

use App\Models\Application;

final class ApplicationUrlGenerator
{
    public function cvViewUrl(Application $application): string
    {
        return route('employer.candidate.detail', ['applicationId' => $application->id]);
    }

    public function chatUrl(Application $application): string
    {
        return route('employer.candidate.detail', ['applicationId' => $application->id]);
    }
}

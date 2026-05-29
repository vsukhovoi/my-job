<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AddonSettingsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return array_filter(
            parent::getWidgets(),
            fn (string $widget): bool => $widget !== AddonSettingsWidget::class
        );
    }
}

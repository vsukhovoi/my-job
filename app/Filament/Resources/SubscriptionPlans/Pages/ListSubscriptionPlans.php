<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubscriptionPlans\Pages;

use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Filament\Widgets\AddonSettingsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Новий тариф'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            AddonSettingsWidget::class,
        ];
    }
}

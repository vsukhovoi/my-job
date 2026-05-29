<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\AddonType;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;

class AddonSettingsPage extends Page
{
    protected string $view = 'filament.pages.addon-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Додаткові послуги';
    protected static ?string $title           = 'Додаткові послуги';
    protected static string|UnitEnum|null $navigationGroup = 'Фінанси';
    protected static ?int    $navigationSort  = 25;
    protected static ?string $slug            = 'addon-settings';

    /** @return array<array{addon: AddonType, label: string, price: int, days: int, status: string}> */
    public function getAddons(): array
    {
        return array_map(fn (AddonType $addon) => [
            'addon'  => $addon,
            'label'  => $addon->label(),
            'price'  => $addon->price(),
            'days'   => $addon->durationDays(),
            'status' => $addon === AddonType::CvAccess ? 'В розробці' : 'Активна',
        ], AddonType::cases());
    }
}

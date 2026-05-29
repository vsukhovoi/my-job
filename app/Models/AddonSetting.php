<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddonType;
use Illuminate\Database\Eloquent\Model;

class AddonSetting extends Model
{
    protected $fillable = ['addon_type', 'price'];

    protected static function booted(): void
    {
        static::saved(fn (self $setting) => static::clearPriceCache($setting->addon_type));
    }

    public static function priceFor(AddonType $addon): int
    {
        return (int) cache()->remember(
            "addon_price:{$addon->value}",
            3600,
            fn () => static::where('addon_type', $addon->value)->value('price') ?? $addon->defaultPrice()
        );
    }

    public static function clearPriceCache(string $addonType): void
    {
        cache()->forget("addon_price:{$addonType}");
    }
}

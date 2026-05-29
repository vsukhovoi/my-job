<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AddonType;
use App\Models\AddonSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddonPriceCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function price_is_cached_after_first_read(): void
    {
        Cache::flush();

        $this->assertFalse(Cache::has('addon_price:hot'));

        AddonType::Hot->price();

        $this->assertTrue(Cache::has('addon_price:hot'));
        $this->assertSame(199, Cache::get('addon_price:hot'));
    }

    #[Test]
    public function price_cache_is_invalidated_when_addon_settings_updated(): void
    {
        AddonType::Hot->price(); // prime cache

        AddonSetting::where('addon_type', 'hot')->first()->update(['price' => 250]);

        $this->assertSame(250, AddonType::Hot->price());
    }

    #[Test]
    public function other_addon_cache_is_not_affected_when_one_is_updated(): void
    {
        AddonType::Hot->price();
        AddonType::Top->price();

        AddonSetting::where('addon_type', 'hot')->first()->update(['price' => 300]);

        $this->assertSame(300, AddonType::Hot->price());
        $this->assertSame(299, AddonType::Top->price()); // untouched
    }
}

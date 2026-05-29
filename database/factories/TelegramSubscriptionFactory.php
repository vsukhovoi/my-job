<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\TelegramSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramSubscription>
 */
class TelegramSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'telegram_id' => fake()->numerify('##########'),
            'category_id' => Category::factory(),
        ];
    }
}

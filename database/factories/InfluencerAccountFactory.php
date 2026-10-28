<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InfluencerAccount>
 */
class InfluencerAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'influencer_id' => Influencer::factory(),
            'platform' => fake()->randomElement(Platform::cases()),
            'handle' => '@'.fake()->unique()->userName(),
            'external_id' => fake()->unique()->uuid(),
            'followers' => fake()->numberBetween(10_000, 50_000_000),
        ];
    }
}

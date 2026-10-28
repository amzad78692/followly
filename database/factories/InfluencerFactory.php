<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Influencer>
 */
class InfluencerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'name' => $name,
            'real_name' => $name,
            'bio' => fake()->sentence(12),
            'avatar_url' => 'https://i.pravatar.cc/300?u='.urlencode($name),
            'language' => 'English',
            'is_verified' => fake()->boolean(70),
        ];
    }

    public function verified(): static
    {
        return $this->state(['is_verified' => true]);
    }

    /**
     * Give the influencer one account on each of the given platforms.
     */
    public function withAccounts(array $platforms = [Platform::YouTube, Platform::Instagram]): static
    {
        return $this->afterCreating(function (Influencer $influencer) use ($platforms) {
            foreach ($platforms as $platform) {
                InfluencerAccount::factory()->for($influencer)->create(['platform' => $platform]);
            }
        });
    }
}

<?php

namespace Database\Factories;

use App\Enums\PostType;
use App\Models\InfluencerAccount;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $seed = fake()->unique()->word();

        return [
            'influencer_account_id' => InfluencerAccount::factory(),
            'external_id' => fake()->unique()->uuid(),
            'type' => PostType::Video,
            'title' => fake()->sentence(5),
            'body' => fake()->sentence(12),
            'media' => ['images' => ["https://picsum.photos/seed/{$seed}/800/450"]],
            'duration_seconds' => fake()->numberBetween(30, 3600),
            'published_at' => fake()->dateTimeBetween('-7 days'),
        ];
    }

    public function text(): static
    {
        return $this->state(['type' => PostType::Text, 'title' => null, 'media' => null, 'duration_seconds' => null]);
    }
}

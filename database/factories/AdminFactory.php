<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => "Admin",
            'email' => "admin@gmail.com",
            'mobile' => "22650262",
            'email_verified_at' => now(),
            'password' => bcrypt('Admin@123'),
        ];
    }
}

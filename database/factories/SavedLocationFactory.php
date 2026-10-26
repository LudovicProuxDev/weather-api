<?php

namespace Database\Factories;

use App\Models\SavedLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedLocation>
 */
class SavedLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'city_id' => fake()->unique()->numberBetween(1_000_000, 9_999_999),
            'name' => fake()->city(),
            'region' => fake()->randomElement(['Bretagne', 'Occitanie', 'Nouvelle-Aquitaine', 'Grand Est']),
            'department' => fake()->city(),
            'country' => 'France',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}

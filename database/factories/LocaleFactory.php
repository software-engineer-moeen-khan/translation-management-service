<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Locale>
 */
class LocaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??') . '-' . fake()->unique()->numerify('###'),
            'name' => fake()->word(),
        ];
    }
}

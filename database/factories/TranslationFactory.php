<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale_id' => Locale::factory(),
            'key' => implode('.', [fake()->word(), fake()->word(), fake()->unique()->lexify('????????')]),
            'content' => fake()->sentence(),
        ];
    }
}

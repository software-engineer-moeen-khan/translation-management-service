<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private const LOCALES = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish'];

    private const TAGS = ['mobile', 'desktop', 'web'];

    /**
     * Seed the reference data the service starts with.
     */
    public function run(): void
    {
        foreach (self::LOCALES as $code => $name) {
            Locale::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (self::TAGS as $name) {
            Tag::query()->firstOrCreate(['name' => $name]);
        }

        // A well-known login is a convenience for local evaluation only.
        // Real environments create users with `php artisan user:create`.
        if (! app()->isProduction()) {
            User::query()->firstOrCreate(
                ['email' => 'admin@example.com'],
                ['name' => 'Admin', 'password' => 'password'],
            );
        }
    }
}

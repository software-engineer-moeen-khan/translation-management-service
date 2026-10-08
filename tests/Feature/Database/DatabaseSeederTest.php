<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_reference_data_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(['en', 'es', 'fr'], Locale::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['desktop', 'mobile', 'web'], Tag::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
    }

    public function test_it_does_not_create_the_demo_user_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(3, Locale::query()->count());
    }
}

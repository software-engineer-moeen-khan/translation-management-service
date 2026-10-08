<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedTranslationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_requested_number_of_translations(): void
    {
        $this->artisan('translations:seed', ['--count' => 250, '--chunk' => 100])
            ->expectsOutputToContain('Seeded 250 translations across 3 locale(s)')
            ->assertSuccessful();

        $this->assertSame(250, Translation::query()->count());
        $this->assertSame(['en', 'es', 'fr'], Locale::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['desktop', 'mobile', 'web'], Tag::query()->orderBy('name')->pluck('name')->all());

        // Rows are spread evenly over the locales.
        foreach (Locale::query()->withCount('translations')->get() as $locale) {
            $this->assertEqualsWithDelta(250 / 3, $locale->translations_count, 1);
        }
    }

    public function test_every_seeded_translation_is_tagged(): void
    {
        $this->artisan('translations:seed', ['--count' => 120])->assertSuccessful();

        $this->assertSame(0, Translation::query()->doesntHave('tags')->count());
        $this->assertGreaterThan(120, DB::table('tag_translation')->count());
        $this->assertSame(3, DB::table('tag_translation')->distinct()->count('tag_id'));
    }

    public function test_it_can_target_specific_locales(): void
    {
        $this->artisan('translations:seed', ['--count' => 10, '--locales' => 'de, ja'])->assertSuccessful();

        $this->assertSame(['de' => 'German', 'ja' => 'JA'], Locale::query()->pluck('name', 'code')->all());
        $this->assertSame(5, Locale::query()->where('code', 'ja')->first()->translations()->count());
    }

    public function test_it_can_be_run_repeatedly(): void
    {
        $this->artisan('translations:seed', ['--count' => 50])->assertSuccessful();
        $this->artisan('translations:seed', ['--count' => 50])->assertSuccessful();

        $this->assertSame(100, Translation::query()->count());
        $this->assertSame(0, Translation::query()->doesntHave('tags')->count());
    }

    public function test_it_invalidates_exports_of_the_seeded_locales(): void
    {
        $en = Locale::factory()->create(['code' => 'en']);
        $de = Locale::factory()->create(['code' => 'de']);

        $this->artisan('translations:seed', ['--count' => 5, '--locales' => 'en'])->assertSuccessful();

        $this->assertSame(2, $en->refresh()->export_version);
        $this->assertSame(1, $de->refresh()->export_version);
    }

    public function test_it_rejects_invalid_options(): void
    {
        $this->artisan('translations:seed', ['--count' => 0])->assertExitCode(2);
        $this->artisan('translations:seed', ['--chunk' => 0])->assertExitCode(2);
        $this->artisan('translations:seed', ['--chunk' => 5001])->assertExitCode(2);
        $this->artisan('translations:seed', ['--locales' => ' , '])->assertExitCode(2);

        $this->assertSame(0, Translation::query()->count());
    }
}

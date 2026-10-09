<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\Locale;
use App\Repositories\EloquentLocaleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EloquentLocaleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bumps_each_given_locale_once_in_a_single_statement(): void
    {
        $en = Locale::factory()->create();
        $fr = Locale::factory()->create();
        $untouched = Locale::factory()->create();

        DB::enableQueryLog();
        (new EloquentLocaleRepository())->bumpExportVersion($en->id, $fr->id, $en->id);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame(2, $en->refresh()->export_version);
        $this->assertSame(2, $fr->refresh()->export_version);
        $this->assertSame(1, $untouched->refresh()->export_version);
    }

    public function test_bumping_nothing_does_not_touch_the_database(): void
    {
        DB::enableQueryLog();

        (new EloquentLocaleRepository())->bumpExportVersion();

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_it_finds_a_locale_by_code(): void
    {
        $locale = Locale::factory()->create(['code' => 'pt-BR']);
        $repository = new EloquentLocaleRepository();

        $this->assertTrue($repository->findByCode('pt-BR')->is($locale));
        $this->assertNull($repository->findByCode('xx'));
    }
}

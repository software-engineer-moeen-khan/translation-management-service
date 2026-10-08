<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TranslationRepository;
use App\Enums\ExportFormat;
use App\Models\Locale;
use App\Services\TranslationExportService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TranslationExportServiceTest extends TestCase
{
    public function test_it_builds_the_payload_once_per_export_version(): void
    {
        $locale = $this->locale(version: 1);
        $repository = Mockery::mock(TranslationRepository::class);
        $repository->shouldReceive('pairsForLocale')->once()->with(7, [])->andReturn(['a.b' => 'one']);
        $repository->shouldReceive('pairsForLocale')->once()->with(7, [])->andReturn(['a.b' => 'two']);
        $service = $this->service($repository);

        $this->assertSame('{"a.b":"one"}', $service->json($locale, ExportFormat::Flat));
        $this->assertSame('{"a.b":"one"}', $service->json($locale, ExportFormat::Flat));

        $locale->export_version = 2;

        $this->assertSame('{"a.b":"two"}', $service->json($locale, ExportFormat::Flat));
    }

    public function test_formats_and_tag_sets_are_cached_separately(): void
    {
        $locale = $this->locale();
        $repository = Mockery::mock(TranslationRepository::class);
        $repository->shouldReceive('pairsForLocale')->twice()->with(7, [])->andReturn(['a.b' => 'x']);
        $repository->shouldReceive('pairsForLocale')->once()->with(7, ['web'])->andReturn([]);
        $service = $this->service($repository);

        $this->assertSame('{"a.b":"x"}', $service->json($locale, ExportFormat::Flat));
        $this->assertSame('{"a":{"b":"x"}}', $service->json($locale, ExportFormat::Nested));
        $this->assertSame('{}', $service->json($locale, ExportFormat::Flat, ['web']));
    }

    public function test_the_etag_tracks_version_format_and_tags_but_not_tag_order(): void
    {
        $service = $this->service(Mockery::mock(TranslationRepository::class));
        $locale = $this->locale(version: 1);

        $etag = $service->etag($locale, ExportFormat::Flat, ['web', 'mobile']);

        $this->assertSame($etag, $service->etag($locale, ExportFormat::Flat, ['mobile', 'web']));
        $this->assertNotSame($etag, $service->etag($locale, ExportFormat::Nested, ['web', 'mobile']));
        $this->assertNotSame($etag, $service->etag($locale, ExportFormat::Flat, ['web']));
        $this->assertNotSame($etag, $service->etag($this->locale(version: 2), ExportFormat::Flat, ['web', 'mobile']));
    }

    public function test_malformed_utf8_does_not_break_the_export(): void
    {
        $repository = Mockery::mock(TranslationRepository::class);
        $repository->shouldReceive('pairsForLocale')->andReturn(['a' => "bad \xB1 byte"]);

        $json = $this->service($repository)->json($this->locale(), ExportFormat::Flat);

        $this->assertSame(['a' => "bad \u{FFFD} byte"], json_decode($json, true));
    }

    private function service(TranslationRepository&MockInterface $repository): TranslationExportService
    {
        return new TranslationExportService($repository, new Repository(new ArrayStore()));
    }

    private function locale(int $version = 1): Locale
    {
        $locale = new Locale(['code' => 'en', 'name' => 'English']);
        $locale->id = 7;
        $locale->export_version = $version;

        return $locale;
    }
}

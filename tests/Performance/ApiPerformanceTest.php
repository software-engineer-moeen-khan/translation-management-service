<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Models\Locale;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Exercises every endpoint against 100k translations and holds each one to
 * the response-time budget from the requirements.
 *
 * Requests are dispatched in-process (no network or web server), so the
 * numbers reflect application and database time. Each measurement is the
 * median of several runs to keep one slow run from failing the build.
 *
 * Run with PERFORMANCE_REPORT=1 to print the measured timings.
 */
#[Group('performance')]
class ApiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const RECORDS = 100_000;

    private const ENDPOINT_BUDGET_MS = 200;

    private const EXPORT_BUDGET_MS = 500;

    private const RUNS = 5;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = User::factory()->create()->createToken('performance')->plainTextToken;
    }

    public function test_read_endpoints_respond_within_budget(): void
    {
        $this->seedTranslations('en,fr,es');
        $id = (int) Translation::query()->max('id') - 10;

        $endpoints = [
            'list' => '/api/v1/translations',
            'list (100 per page)' => '/api/v1/translations?per_page=100',
            'search by key' => '/api/v1/translations?key=checkout.button',
            'search by key (no match)' => '/api/v1/translations?key=does-not-exist',
            'search by content' => '/api/v1/translations?content=payment',
            'search by content (no match)' => '/api/v1/translations?content=zzzzzz',
            'search by tag' => '/api/v1/translations?tags=mobile',
            'search by locale' => '/api/v1/translations?locale=fr',
            'search with all filters' => '/api/v1/translations?locale=en&key=auth&content=welcome&tags=web,desktop',
            'show' => "/api/v1/translations/{$id}",
            'locales' => '/api/v1/locales',
            'tags' => '/api/v1/tags',
        ];

        $this->warmUp();

        foreach ($endpoints as $name => $url) {
            $this->assertWithinBudget($name, self::ENDPOINT_BUDGET_MS, function () use ($url): void {
                $this->api('GET', $url)->assertOk();
            });
        }
    }

    public function test_a_page_deep_into_the_table_responds_within_budget(): void
    {
        $this->seedTranslations('en,fr,es');
        $this->warmUp();

        // Reuse the cursor format the API hands out, pointed 500 rows before the end.
        $next = Cursor::fromEncoded($this->api('GET', '/api/v1/translations')->json('meta.next_cursor'));
        $column = array_key_first($next->toArray());
        $deep = (new Cursor([$column => Translation::query()->max('id') - 500]))->encode();

        $this->assertWithinBudget('list (page near the end)', self::ENDPOINT_BUDGET_MS, function () use ($deep): void {
            $this->api('GET', "/api/v1/translations?cursor={$deep}")->assertOk()->assertJsonCount(25, 'data');
        });
    }

    public function test_write_endpoints_respond_within_budget(): void
    {
        $this->seedTranslations('en,fr,es');
        $this->warmUp();
        $counter = 0;

        $this->assertWithinBudget('create', self::ENDPOINT_BUDGET_MS, function () use (&$counter): void {
            $this->api('POST', '/api/v1/translations', [
                'locale' => 'en',
                'key' => 'performance.created_' . ++$counter,
                'content' => 'Created during the performance test',
                'tags' => ['web', 'performance'],
            ])->assertCreated();
        });

        $ids = Translation::query()->where('key', 'like', 'performance.created%')->pluck('id')->all();

        $this->assertWithinBudget('update', self::ENDPOINT_BUDGET_MS, function () use ($ids, &$counter): void {
            $this->api('PATCH', "/api/v1/translations/{$ids[0]}", [
                'content' => 'Updated ' . ++$counter,
                'tags' => $counter % 2 === 0 ? ['mobile'] : ['desktop'],
            ])->assertOk();
        });

        $this->assertWithinBudget('delete', self::ENDPOINT_BUDGET_MS, function () use (&$ids): void {
            $this->api('DELETE', '/api/v1/translations/' . array_pop($ids))->assertNoContent();
        });

        $email = User::query()->value('email');

        $this->assertWithinBudget('login', self::ENDPOINT_BUDGET_MS, function () use ($email): void {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->assertOk();
        });
    }

    public function test_export_of_a_large_locale_responds_within_budget(): void
    {
        // The worst case for the export: all 100k rows belong to one locale.
        $this->seedTranslations('en');
        $this->warmUp();

        $this->assertSame(self::RECORDS, Locale::query()->where('code', 'en')->firstOrFail()->translations()->count());

        // With the cache disabled every request reads and encodes all rows.
        config(['translations.export.cache_ttl' => 0]);

        $this->assertWithinBudget('export, uncached (flat)', self::EXPORT_BUDGET_MS, function (): void {
            $response = $this->api('GET', '/api/v1/export/en')->assertOk();

            $this->assertCount(self::RECORDS, json_decode($response->getContent(), true));
        });

        $this->assertWithinBudget('export, uncached (nested)', self::EXPORT_BUDGET_MS, function (): void {
            $this->api('GET', '/api/v1/export/en?format=nested')->assertOk();
        });

        $this->assertWithinBudget('export, uncached (by tag)', self::EXPORT_BUDGET_MS, function (): void {
            $this->api('GET', '/api/v1/export/en?tags=web')->assertOk();
        });

        config(['translations.export.cache_ttl' => 3600]);
        $etag = $this->api('GET', '/api/v1/export/en')->assertOk()->headers->get('ETag');

        $this->assertWithinBudget('export, cached', self::EXPORT_BUDGET_MS, function (): void {
            $this->api('GET', '/api/v1/export/en')->assertOk();
        });

        $this->assertWithinBudget('export, not modified (304)', self::EXPORT_BUDGET_MS, function () use ($etag): void {
            $this->api('GET', '/api/v1/export/en', [], ['If-None-Match' => $etag])->assertStatus(304);
        });
    }

    public function test_export_is_current_and_within_budget_right_after_a_write(): void
    {
        $this->seedTranslations('en');
        $this->warmUp();
        $this->api('GET', '/api/v1/export/en')->assertOk();

        // Each write invalidates the cached export; the next read must rebuild it.
        foreach (range(1, self::RUNS) as $run) {
            $key = "performance.fresh_{$run}";

            $this->api('POST', '/api/v1/translations', ['locale' => 'en', 'key' => $key, 'content' => 'Fresh'])
                ->assertCreated();

            $started = hrtime(true);
            $response = $this->api('GET', '/api/v1/export/en')->assertOk();
            $elapsed = (hrtime(true) - $started) / 1e6;

            $this->report('export right after a write', $elapsed, $elapsed);
            $this->assertStringContainsString("\"{$key}\":\"Fresh\"", $response->getContent());
            $this->assertLessThan(self::EXPORT_BUDGET_MS, $elapsed);
        }
    }

    private function seedTranslations(string $locales): void
    {
        $this->artisan('translations:seed', ['--count' => self::RECORDS, '--locales' => $locales])
            ->assertSuccessful();
    }

    /**
     * The first request through a freshly booted application pays one-off
     * costs (class loading, route compilation) that a running server does not.
     */
    private function warmUp(): void
    {
        $this->api('GET', '/api/v1/locales')->assertOk();
        $this->api('GET', '/api/v1/translations?per_page=1')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function api(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withToken($this->token)->json($method, $uri, $data, $headers);
    }

    private function assertWithinBudget(string $name, int $budgetMs, callable $request): void
    {
        $timings = [];

        for ($run = 0; $run < self::RUNS; $run++) {
            $started = hrtime(true);
            $request();
            $timings[] = (hrtime(true) - $started) / 1e6;
        }

        sort($timings);
        $median = $timings[intdiv(count($timings), 2)];

        $this->report($name, $median, end($timings));
        $this->assertLessThan(
            $budgetMs,
            $median,
            sprintf('"%s" took %.1f ms (median of %d runs); the budget is %d ms.', $name, $median, self::RUNS, $budgetMs),
        );
    }

    private function report(string $name, float $median, float $max): void
    {
        if (getenv('PERFORMANCE_REPORT')) {
            fwrite(STDERR, sprintf("  %-30s median %6.1f ms   max %6.1f ms\n", $name, $median, $max));
        }
    }
}

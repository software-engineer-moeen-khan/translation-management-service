<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranslationExportTest extends TestCase
{
    use RefreshDatabase;

    private Locale $en;

    protected function setUp(): void
    {
        parent::setUp();

        $this->en = Locale::factory()->create(['code' => 'en']);

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_export_requires_a_token_by_default(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/export/en')->assertUnauthorized();
    }

    public function test_it_exports_a_locale_as_a_flat_key_value_document(): void
    {
        $fr = Locale::factory()->create(['code' => 'fr']);
        $this->translation($this->en, 'auth.login.title', 'Sign in');
        $this->translation($this->en, 'home.title', 'Home');
        $this->translation($fr, 'home.title', 'Accueil');

        $this->getJson('/api/v1/export/en')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Content-Language', 'en')
            ->assertExactJson(['auth.login.title' => 'Sign in', 'home.title' => 'Home']);
    }

    public function test_it_exports_a_nested_document(): void
    {
        $this->translation($this->en, 'auth.login.title', 'Sign in');
        $this->translation($this->en, 'auth.login.submit', 'Continue');
        $this->translation($this->en, 'errors.404', 'Not found');
        $this->translation($this->en, 'title', 'App');

        $this->getJson('/api/v1/export/en?format=nested')
            ->assertOk()
            ->assertExactJson([
                'auth' => ['login' => ['title' => 'Sign in', 'submit' => 'Continue']],
                'errors' => ['404' => 'Not found'],
                'title' => 'App',
            ]);
    }

    public function test_it_exports_only_translations_with_the_requested_tags(): void
    {
        $this->translation($this->en, 'a.web', 'Web', ['web']);
        $this->translation($this->en, 'a.both', 'Both', ['web', 'mobile']);
        $this->translation($this->en, 'a.desktop', 'Desktop', ['desktop']);
        $this->translation($this->en, 'a.untagged', 'Untagged');

        $this->getJson('/api/v1/export/en?tags=mobile')
            ->assertOk()
            ->assertExactJson(['a.both' => 'Both']);

        $this->getJson('/api/v1/export/en?tags=mobile,desktop')
            ->assertOk()
            ->assertExactJson(['a.both' => 'Both', 'a.desktop' => 'Desktop']);
    }

    public function test_an_empty_export_is_an_empty_object(): void
    {
        $response = $this->getJson('/api/v1/export/en')->assertOk();

        $this->assertSame('{}', $response->getContent());
    }

    public function test_content_is_exported_verbatim(): void
    {
        $this->translation($this->en, 'greeting', 'Héllo <b>wörld</b> / 你好 "quoted"');

        $response = $this->getJson('/api/v1/export/en')->assertOk();

        $this->assertSame('{"greeting":"Héllo <b>wörld</b> / 你好 \"quoted\""}', $response->getContent());
    }

    public function test_unknown_locales_return_404(): void
    {
        $this->getJson('/api/v1/export/xx')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_it_validates_the_format(): void
    {
        $this->getJson('/api/v1/export/en?format=xml')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');
    }

    public function test_export_reflects_every_write_immediately(): void
    {
        $this->getJson('/api/v1/export/en')->assertExactJson([]);

        $id = $this->postJson('/api/v1/translations', [
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Home',
            'tags' => ['web'],
        ])->json('data.id');
        $this->getJson('/api/v1/export/en')->assertExactJson(['home.title' => 'Home']);
        $this->getJson('/api/v1/export/en?tags=mobile')->assertExactJson([]);

        $this->patchJson("/api/v1/translations/{$id}", ['content' => 'Homepage']);
        $this->getJson('/api/v1/export/en')->assertExactJson(['home.title' => 'Homepage']);

        $this->patchJson("/api/v1/translations/{$id}", ['tags' => ['mobile']]);
        $this->getJson('/api/v1/export/en?tags=mobile')->assertExactJson(['home.title' => 'Homepage']);

        $this->deleteJson("/api/v1/translations/{$id}");
        $this->getJson('/api/v1/export/en')->assertExactJson([]);
    }

    public function test_repeated_exports_are_served_from_the_cache(): void
    {
        $this->translation($this->en, 'home.title', 'Home');
        $this->getJson('/api/v1/export/en')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/v1/export/en')->assertOk()->assertExactJson(['home.title' => 'Home']);

        // Only the locale (and its export version) is read; translations are not.
        $queries = array_column(DB::getQueryLog(), 'query');
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('"locales"', $queries[0]);
    }

    public function test_caching_can_be_disabled(): void
    {
        config(['translations.export.cache_ttl' => 0]);
        $this->translation($this->en, 'home.title', 'Home');
        $this->getJson('/api/v1/export/en')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/v1/export/en')->assertOk()->assertExactJson(['home.title' => 'Home']);

        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_it_answers_conditional_requests_with_304_until_something_changes(): void
    {
        $translation = $this->translation($this->en, 'home.title', 'Home');

        $etag = $this->getJson('/api/v1/export/en')->assertOk()->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $notModified = $this->getJson('/api/v1/export/en', ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertSame('', $notModified->getContent());
        $this->assertSame($etag, $notModified->headers->get('ETag'));

        $this->patchJson("/api/v1/translations/{$translation->id}", ['content' => 'Homepage']);

        $fresh = $this->getJson('/api/v1/export/en', ['If-None-Match' => $etag])
            ->assertOk()
            ->assertExactJson(['home.title' => 'Homepage']);
        $this->assertNotSame($etag, $fresh->headers->get('ETag'));
    }

    public function test_each_variant_of_an_export_has_its_own_etag(): void
    {
        Locale::factory()->create(['code' => 'fr']);

        $etags = array_map(
            fn (string $url): ?string => $this->getJson($url)->assertOk()->headers->get('ETag'),
            [
                '/api/v1/export/en',
                '/api/v1/export/en?format=nested',
                '/api/v1/export/en?tags=web',
                '/api/v1/export/en?tags=web,mobile',
                '/api/v1/export/fr',
            ],
        );

        $this->assertCount(5, array_unique($etags));

        // Tag order does not create a separate variant.
        $this->assertSame($etags[3], $this->getJson('/api/v1/export/en?tags=mobile,web')->headers->get('ETag'));
    }

    public function test_private_exports_may_only_be_cached_by_the_client(): void
    {
        $cacheControl = $this->getJson('/api/v1/export/en')->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_a_public_export_needs_no_token_and_is_cacheable_by_a_cdn(): void
    {
        config(['translations.export.public' => true, 'translations.export.cdn_max_age' => 30]);
        $this->app['auth']->forgetGuards();
        $this->translation($this->en, 'home.title', 'Home');

        $response = $this->getJson('/api/v1/export/en')
            ->assertOk()
            ->assertExactJson(['home.title' => 'Home']);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('s-maxage=30', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertNotEmpty($response->headers->get('ETag'));

        // Making the export public does not open up the rest of the API.
        $this->getJson('/api/v1/translations')->assertUnauthorized();
    }

    /**
     * @param  list<string>  $tags
     */
    private function translation(Locale $locale, string $key, string $content, array $tags = []): Translation
    {
        $translation = Translation::factory()->for($locale)->create(['key' => $key, 'content' => $content]);

        foreach ($tags as $name) {
            $translation->tags()->attach(Tag::query()->firstOrCreate(['name' => $name]));
        }

        return $translation;
    }
}

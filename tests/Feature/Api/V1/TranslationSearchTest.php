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

class TranslationSearchTest extends TestCase
{
    use RefreshDatabase;

    private Locale $en;

    private Locale $fr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->en = Locale::factory()->create(['code' => 'en']);
        $this->fr = Locale::factory()->create(['code' => 'fr']);

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_search_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/translations')->assertUnauthorized();
    }

    public function test_it_lists_translations_in_a_stable_order(): void
    {
        $first = $this->translation($this->en, 'home.title', 'Home');
        $second = $this->translation($this->fr, 'home.title', 'Accueil');

        $this->getJson('/api/v1/translations')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $second->id])
            ->assertJsonStructure([
                'data' => [['id', 'key', 'locale', 'content', 'tags', 'created_at', 'updated_at']],
                'links' => ['next', 'prev'],
                'meta' => ['per_page', 'next_cursor', 'prev_cursor'],
            ]);
    }

    public function test_it_searches_by_key(): void
    {
        $match = $this->translation($this->en, 'auth.login.title', 'Sign in');
        $this->translation($this->en, 'auth.logout.title', 'Sign out');

        $this->getJson('/api/v1/translations?key=login')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$match->id]);
    }

    public function test_key_search_treats_wildcards_literally(): void
    {
        $match = $this->translation($this->en, 'form.save_button', 'Save');
        $this->translation($this->en, 'form.saveXbutton', 'Save');
        $this->translation($this->en, 'form.other', '100% done');

        $this->getJson('/api/v1/translations?key=save_button')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$match->id]);

        $this->getJson('/api/v1/translations?key=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/translations?key=!')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_searches_by_content(): void
    {
        $match = $this->translation($this->en, 'home.welcome', 'Welcome back, friend');
        $this->translation($this->en, 'home.bye', 'See you soon');

        $this->getJson('/api/v1/translations?content=welcome')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$match->id]);

        $this->getJson('/api/v1/translations?content=' . urlencode('100%'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_searches_by_tags(): void
    {
        $web = $this->translation($this->en, 'a.web', 'x', ['web']);
        $both = $this->translation($this->en, 'a.both', 'x', ['web', 'mobile']);
        $desktop = $this->translation($this->en, 'a.desktop', 'x', ['desktop']);
        $this->translation($this->en, 'a.untagged', 'x');

        $this->getJson('/api/v1/translations?tags=web')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$web->id, $both->id]);

        // Several tags match translations carrying any of them, each listed once.
        $this->getJson('/api/v1/translations?tags=Mobile,%20desktop')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$both->id, $desktop->id]);

        $this->getJson('/api/v1/translations?tags[]=web&tags[]=mobile')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$web->id, $both->id]);

        $this->getJson('/api/v1/translations?tags=unknown')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_filters_by_locale(): void
    {
        $this->translation($this->en, 'home.title', 'Home');
        $french = $this->translation($this->fr, 'home.title', 'Accueil');

        $this->getJson('/api/v1/translations?locale=fr')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$french->id]);

        $this->getJson('/api/v1/translations?locale=xx')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filters_are_combined(): void
    {
        $match = $this->translation($this->en, 'checkout.pay', 'Pay now', ['web']);
        $this->translation($this->en, 'checkout.pay_later', 'Pay later', ['mobile']);
        $this->translation($this->fr, 'checkout.pay', 'Payer maintenant', ['web']);
        $this->translation($this->en, 'cart.pay', 'Pay now', ['web']);

        $this->getJson('/api/v1/translations?locale=en&key=checkout&content=now&tags=web')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$match->id]);
    }

    public function test_empty_filters_are_ignored(): void
    {
        $this->translation($this->en, 'home.title', 'Home');

        $this->getJson('/api/v1/translations?key=&content=&tags=&locale=')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_results_are_cursor_paginated_and_keep_the_filters(): void
    {
        $ids = collect(range(1, 5))
            ->map(fn (int $i): int => $this->translation($this->en, "list.item_{$i}", 'x')->id)
            ->all();
        $this->translation($this->en, 'other.item', 'x');

        $first = $this->getJson('/api/v1/translations?key=list&per_page=2')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$ids[0], $ids[1]])
            ->assertJsonPath('meta.per_page', 2);

        $next = $first->json('links.next');
        $this->assertStringContainsString('key=list', $next);
        $this->assertStringContainsString('per_page=2', $next);

        $second = $this->getJson($next)->assertOk()->assertJsonPath('data.*.id', [$ids[2], $ids[3]]);

        $this->getJson($second->json('links.next'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$ids[4]])
            ->assertJsonPath('links.next', null);
    }

    public function test_page_size_defaults_and_is_bounded(): void
    {
        $this->getJson('/api/v1/translations')->assertOk()->assertJsonPath('meta.per_page', 25);

        $this->getJson('/api/v1/translations?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/v1/translations?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_it_validates_filters(): void
    {
        $this->getJson('/api/v1/translations?key[]=a&content[]=b&tags[][]=c')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'content', 'tags.0']);
    }

    public function test_listing_does_not_issue_a_query_per_row(): void
    {
        foreach (range(1, 10) as $i) {
            $this->translation($i % 2 ? $this->en : $this->fr, "list.item_{$i}", 'x', ['web', 'mobile']);
        }

        DB::enableQueryLog();
        $this->getJson('/api/v1/translations')->assertOk()->assertJsonCount(10, 'data');

        // One query each for translations, their locales and their tags.
        $this->assertCount(3, DB::getQueryLog());
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

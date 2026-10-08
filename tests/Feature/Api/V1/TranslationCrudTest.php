<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslationCrudTest extends TestCase
{
    use RefreshDatabase;

    private Locale $en;

    protected function setUp(): void
    {
        parent::setUp();

        $this->en = Locale::factory()->create(['code' => 'en', 'name' => 'English']);
    }

    public function test_endpoints_require_authentication(): void
    {
        $translation = Translation::factory()->for($this->en)->create();

        $this->postJson('/api/v1/translations', [])->assertUnauthorized();
        $this->getJson("/api/v1/translations/{$translation->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/translations/{$translation->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/translations/{$translation->id}")->assertUnauthorized();
    }

    public function test_it_creates_a_translation_with_tags(): void
    {
        $this->signIn();
        $existing = Tag::factory()->create(['name' => 'web']);

        $response = $this->postJson('/api/v1/translations', [
            'locale' => 'en',
            'key' => 'auth.login.title',
            'content' => 'Sign in',
            'tags' => ['Web', 'mobile'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'auth.login.title')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.content', 'Sign in')
            ->assertJsonPath('data.tags', ['mobile', 'web'])
            ->assertJsonStructure(['data' => ['id', 'created_at', 'updated_at']]);

        $id = $response->json('data.id');
        $response->assertHeader('Location', url("/api/v1/translations/{$id}"));

        $this->assertDatabaseHas('translations', [
            'id' => $id,
            'locale_id' => $this->en->id,
            'key' => 'auth.login.title',
            'content' => 'Sign in',
        ]);
        // "Web" is normalised and matched to the existing tag instead of duplicated.
        $this->assertSame(2, Tag::query()->count());
        $this->assertDatabaseHas('tag_translation', ['translation_id' => $id, 'tag_id' => $existing->id]);
    }

    public function test_it_creates_a_translation_without_tags(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/translations', ['locale' => 'en', 'key' => 'home.title', 'content' => 'Home'])
            ->assertCreated()
            ->assertJsonPath('data.tags', []);
    }

    public function test_the_same_key_can_exist_in_another_locale(): void
    {
        $this->signIn();
        Locale::factory()->create(['code' => 'fr']);
        Translation::factory()->for($this->en)->create(['key' => 'home.title']);

        $this->postJson('/api/v1/translations', ['locale' => 'fr', 'key' => 'home.title', 'content' => 'Accueil'])
            ->assertCreated();
    }

    public function test_a_key_is_unique_within_a_locale(): void
    {
        $this->signIn();
        Translation::factory()->for($this->en)->create(['key' => 'home.title']);

        $this->postJson('/api/v1/translations', ['locale' => 'en', 'key' => 'home.title', 'content' => 'Home'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.key.0', 'The key already exists for this locale.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_invalid_payloads(array $overrides, string $field): void
    {
        $this->signIn();

        $payload = array_filter(
            [...['locale' => 'en', 'key' => 'home.title', 'content' => 'Home'], ...$overrides],
            fn (mixed $value): bool => $value !== null,
        );

        $this->postJson('/api/v1/translations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('translations', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing locale' => [['locale' => null], 'locale'],
            'unknown locale' => [['locale' => 'xx'], 'locale'],
            'non-string locale' => [['locale' => ['en']], 'locale'],
            'missing key' => [['key' => null], 'key'],
            'key with spaces' => [['key' => 'home title'], 'key'],
            'key with trailing dot' => [['key' => 'home.'], 'key'],
            'key with markup' => [['key' => '<script>'], 'key'],
            'key too long' => [['key' => str_repeat('a', 192)], 'key'],
            'missing content' => [['content' => null], 'content'],
            'content too long' => [['content' => str_repeat('a', 10001)], 'content'],
            'tags not a list' => [['tags' => 'web'], 'tags'],
            'too many tags' => [['tags' => array_map(fn (int $i): string => "tag{$i}", range(1, 21))], 'tags'],
            'malformed tag' => [['tags' => ['web app']], 'tags.0'],
            'non-string tag' => [['tags' => [['web']]], 'tags.0'],
            'duplicate tags' => [['tags' => ['web', 'WEB']], 'tags.0'],
        ];
    }

    public function test_it_shows_a_translation(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create(['key' => 'home.title', 'content' => 'Home']);
        $translation->tags()->attach(Tag::factory()->create(['name' => 'web']));

        $this->getJson("/api/v1/translations/{$translation->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $translation->id,
                'key' => 'home.title',
                'locale' => 'en',
                'content' => 'Home',
                'tags' => ['web'],
                'created_at' => $translation->created_at->toIso8601String(),
                'updated_at' => $translation->updated_at->toIso8601String(),
            ]]);
    }

    public function test_unknown_translations_return_a_generic_404(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/translations/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);

        $this->getJson('/api/v1/translations/not-a-number')->assertNotFound();
    }

    public function test_it_updates_only_the_fields_that_were_sent(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create(['key' => 'home.title', 'content' => 'Home']);
        $translation->tags()->attach(Tag::factory()->create(['name' => 'web']));

        $this->patchJson("/api/v1/translations/{$translation->id}", ['content' => 'Homepage'])
            ->assertOk()
            ->assertJsonPath('data.content', 'Homepage')
            ->assertJsonPath('data.key', 'home.title')
            ->assertJsonPath('data.tags', ['web']);
    }

    public function test_it_replaces_tags_on_update(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create();
        $translation->tags()->attach(Tag::factory()->create(['name' => 'web']));

        $this->putJson("/api/v1/translations/{$translation->id}", ['tags' => ['mobile', 'desktop']])
            ->assertOk()
            ->assertJsonPath('data.tags', ['desktop', 'mobile']);

        $this->patchJson("/api/v1/translations/{$translation->id}", ['tags' => []])
            ->assertOk()
            ->assertJsonPath('data.tags', []);
    }

    public function test_it_can_rename_a_key_and_move_it_to_another_locale(): void
    {
        $this->signIn();
        $fr = Locale::factory()->create(['code' => 'fr']);
        $translation = Translation::factory()->for($this->en)->create(['key' => 'home.title']);

        $this->patchJson("/api/v1/translations/{$translation->id}", ['locale' => 'fr', 'key' => 'home.heading'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr')
            ->assertJsonPath('data.key', 'home.heading');

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'locale_id' => $fr->id,
            'key' => 'home.heading',
        ]);
    }

    public function test_update_rejects_a_key_that_is_taken_in_the_same_locale(): void
    {
        $this->signIn();
        Translation::factory()->for($this->en)->create(['key' => 'home.title']);
        $translation = Translation::factory()->for($this->en)->create(['key' => 'home.subtitle']);

        $this->patchJson("/api/v1/translations/{$translation->id}", ['key' => 'home.title'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_update_rejects_a_move_to_a_locale_that_already_has_the_key(): void
    {
        $this->signIn();
        $fr = Locale::factory()->create(['code' => 'fr']);
        Translation::factory()->for($fr)->create(['key' => 'home.title']);
        $translation = Translation::factory()->for($this->en)->create(['key' => 'home.title']);

        $this->patchJson("/api/v1/translations/{$translation->id}", ['locale' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_update_accepts_the_translations_own_key(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create(['key' => 'home.title']);

        $this->patchJson("/api/v1/translations/{$translation->id}", ['key' => 'home.title', 'content' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('data.content', 'Hi');
    }

    public function test_update_validates_input(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create();

        $this->patchJson("/api/v1/translations/{$translation->id}", [
            'locale' => 'xx',
            'key' => 'bad key',
            'content' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['locale', 'key', 'content']);
    }

    public function test_it_deletes_a_translation(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create();
        $translation->tags()->attach(Tag::factory()->create());

        $this->deleteJson("/api/v1/translations/{$translation->id}")->assertNoContent();

        $this->assertDatabaseMissing('translations', ['id' => $translation->id]);
        $this->assertDatabaseCount('tag_translation', 0);
    }

    public function test_writes_bump_the_export_version_of_the_affected_locales(): void
    {
        $this->signIn();
        $fr = Locale::factory()->create(['code' => 'fr']);

        $id = $this->postJson('/api/v1/translations', ['locale' => 'en', 'key' => 'a.b', 'content' => 'x'])
            ->json('data.id');
        $this->assertSame(2, $this->en->refresh()->export_version);

        $this->patchJson("/api/v1/translations/{$id}", ['content' => 'y']);
        $this->assertSame(3, $this->en->refresh()->export_version);

        $this->patchJson("/api/v1/translations/{$id}", ['tags' => ['web']]);
        $this->assertSame(4, $this->en->refresh()->export_version);

        // Moving a translation changes the export of both locales.
        $this->patchJson("/api/v1/translations/{$id}", ['locale' => 'fr']);
        $this->assertSame(5, $this->en->refresh()->export_version);
        $this->assertSame(2, $fr->refresh()->export_version);

        $this->deleteJson("/api/v1/translations/{$id}");
        $this->assertSame(3, $fr->refresh()->export_version);
    }

    public function test_a_no_op_update_does_not_bump_the_export_version(): void
    {
        $this->signIn();
        $translation = Translation::factory()->for($this->en)->create(['content' => 'Home']);
        $translation->tags()->attach(Tag::factory()->create(['name' => 'web']));

        $this->patchJson("/api/v1/translations/{$translation->id}", ['content' => 'Home', 'tags' => ['web']])
            ->assertOk();

        $this->assertSame(1, $this->en->refresh()->export_version);
    }

    private function signIn(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }
}

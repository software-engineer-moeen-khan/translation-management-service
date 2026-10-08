<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_locales_require_authentication(): void
    {
        $this->getJson('/api/v1/locales')->assertUnauthorized();
        $this->postJson('/api/v1/locales', ['code' => 'de', 'name' => 'German'])->assertUnauthorized();
    }

    public function test_it_lists_locales_ordered_by_code(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Locale::factory()->create(['code' => 'fr', 'name' => 'French']);
        Locale::factory()->create(['code' => 'en', 'name' => 'English']);

        $this->getJson('/api/v1/locales')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.1.code', 'fr')
            ->assertJsonStructure(['data' => [['id', 'code', 'name']]]);
    }

    public function test_it_adds_a_new_locale(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/locales', ['code' => 'pt-BR', 'name' => 'Portuguese (Brazil)'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'pt-BR')
            ->assertJsonPath('data.name', 'Portuguese (Brazil)');

        $this->assertDatabaseHas('locales', ['code' => 'pt-BR', 'export_version' => 1]);
    }

    public function test_locale_codes_are_unique(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Locale::factory()->create(['code' => 'en']);

        $this->postJson('/api/v1/locales', ['code' => 'en', 'name' => 'English'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_invalid_locales(array $payload, string $field): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/locales', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing code' => [['name' => 'English'], 'code'],
            'missing name' => [['code' => 'en'], 'name'],
            'malformed code' => [['code' => 'english!', 'name' => 'English'], 'code'],
            'code with path characters' => [['code' => '../en', 'name' => 'English'], 'code'],
            'name too long' => [['code' => 'en', 'name' => str_repeat('a', 65)], 'name'],
        ];
    }
}

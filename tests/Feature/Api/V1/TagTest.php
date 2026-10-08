<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_require_authentication(): void
    {
        $this->getJson('/api/v1/tags')->assertUnauthorized();
    }

    public function test_it_lists_tags_alphabetically(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Tag::factory()->create(['name' => 'web']);
        Tag::factory()->create(['name' => 'desktop']);
        Tag::factory()->create(['name' => 'mobile']);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['desktop', 'mobile', 'web'])
            ->assertJsonStructure(['data' => [['id', 'name']], 'meta' => ['next_cursor']]);
    }
}

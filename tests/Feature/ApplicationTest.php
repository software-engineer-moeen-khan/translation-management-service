<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationTest extends TestCase
{
    public function test_the_root_points_to_the_documentation(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('documentation', url('/docs'));
    }

    public function test_the_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_unknown_api_routes_return_a_json_404(): void
    {
        $this->get('/api/v1/nope')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_the_api_documentation_is_published(): void
    {
        $this->assertFileExists(public_path('docs/index.html'));
        $this->assertStringContainsString('openapi: 3.0.3', file_get_contents(public_path('docs/openapi.yaml')));
    }
}

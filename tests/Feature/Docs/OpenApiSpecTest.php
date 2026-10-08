<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

class OpenApiSpecTest extends TestCase
{
    /**
     * Guards against the documentation drifting from the routes that exist.
     */
    public function test_every_api_route_is_documented_and_nothing_else(): void
    {
        $this->assertSame($this->registeredOperations(), $this->documentedOperations());
    }

    /**
     * @return list<string>
     */
    private function registeredOperations(): array
    {
        $operations = [];

        /** @var Route $route */
        foreach (Router::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            // The spec names path parameters after what they hold rather than the model.
            $path = str_replace(
                ['api/v1', '{translation}', '{locale}'],
                ['', '{id}', '{locale}'],
                $route->uri(),
            );

            foreach (array_diff($route->methods(), ['HEAD', 'PUT']) as $method) {
                $operations[] = "{$method} {$path}";
            }
        }

        sort($operations);

        return $operations;
    }

    /**
     * Reads the path and method entries out of the YAML document. The spec is
     * hand-written with a fixed two/four space layout, which keeps this simple.
     *
     * @return list<string>
     */
    private function documentedOperations(): array
    {
        $operations = [];
        $path = null;

        foreach (file(public_path('docs/openapi.yaml'), FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('#^  (/\S*):$#', $line, $match)) {
                $path = $match[1];
            } elseif (preg_match('#^\S#', $line)) {
                $path = null;
            } elseif ($path !== null && preg_match('#^    (get|post|put|patch|delete):$#', $line, $match)) {
                $operations[] = strtoupper($match[1]) . ' ' . $path;
            }
        }

        sort($operations);

        return $operations;
    }
}

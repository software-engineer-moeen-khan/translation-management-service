<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a token for the export unless it has been explicitly made public,
 * which is what allows a CDN to cache and serve it.
 */
class AuthenticateExport
{
    public function __construct(private readonly Authenticate $authenticate)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('translations.export.public')) {
            return $next($request);
        }

        return $this->authenticate->handle($request, $next, 'sanctum');
    }
}

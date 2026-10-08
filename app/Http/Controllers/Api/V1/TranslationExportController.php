<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Translation\ExportTranslationsRequest;
use App\Models\Locale;
use App\Services\TranslationExportService;
use Illuminate\Http\Response;

class TranslationExportController extends Controller
{
    public function __construct(private readonly TranslationExportService $exports)
    {
    }

    public function __invoke(ExportTranslationsRequest $request, Locale $locale): Response
    {
        $format = $request->exportFormat();
        $tags = $request->tags();

        $response = (new Response())
            ->header('Content-Type', 'application/json')
            ->header('Content-Language', $locale->code)
            ->header('Cache-Control', $this->cacheControl())
            ->setEtag($this->exports->etag($locale, $format, $tags));

        // A client or CDN that already holds this version gets an empty 304
        // without the payload being loaded at all.
        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response->setContent($this->exports->json($locale, $format, $tags));
    }

    /**
     * Caches may store the export but must revalidate it, so a changed
     * translation is visible on the very next request.
     */
    private function cacheControl(): string
    {
        if (! config('translations.export.public')) {
            return 'private, no-cache';
        }

        return sprintf('public, max-age=0, s-maxage=%d, must-revalidate', config('translations.export.cdn_max_age'));
    }
}

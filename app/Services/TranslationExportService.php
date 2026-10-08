<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TranslationRepository;
use App\Enums\ExportFormat;
use App\Models\Locale;
use Illuminate\Contracts\Cache\Repository as Cache;

class TranslationExportService
{
    private const JSON_FLAGS = JSON_FORCE_OBJECT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly TranslationRepository $translations,
        private readonly Cache $cache,
    ) {
    }

    /**
     * Validator identifying one exact export payload.
     *
     * It is derived from the locale's export version, which every write bumps,
     * so it can be compared without loading or encoding any translations.
     *
     * @param  list<string>  $tags
     */
    public function etag(Locale $locale, ExportFormat $format, array $tags = []): string
    {
        return sha1($this->fingerprint($locale, $format, $tags));
    }

    /**
     * The export as an encoded JSON document.
     *
     * Payloads are cached per export version: a write moves readers to a new
     * cache key instead of invalidating the old one, so a cached entry is never
     * stale and superseded entries simply expire.
     *
     * @param  list<string>  $tags
     */
    public function json(Locale $locale, ExportFormat $format, array $tags = []): string
    {
        $ttl = (int) config('translations.export.cache_ttl');
        $build = fn (): string => $this->build($locale, $format, $tags);

        if ($ttl <= 0) {
            return $build();
        }

        return $this->cache->remember('translations:export:' . $this->fingerprint($locale, $format, $tags), $ttl, $build);
    }

    /**
     * @param  list<string>  $tags
     */
    private function build(Locale $locale, ExportFormat $format, array $tags): string
    {
        $pairs = $this->translations->pairsForLocale($locale->id, $tags);

        return json_encode($format->shape($pairs), self::JSON_FLAGS);
    }

    /**
     * @param  list<string>  $tags
     */
    private function fingerprint(Locale $locale, ExportFormat $format, array $tags): string
    {
        sort($tags);

        return implode(':', [$locale->id, $locale->export_version, $format->value, sha1(implode(',', $tags))]);
    }
}

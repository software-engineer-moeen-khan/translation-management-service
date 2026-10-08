<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LocaleRepository;
use App\Contracts\TranslationRepository;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    public function __construct(
        private readonly TranslationRepository $translations,
        private readonly LocaleRepository $locales,
    ) {
    }

    /**
     * @param  list<string>  $tags
     */
    public function create(int $localeId, string $key, string $content, array $tags = []): Translation
    {
        return DB::transaction(function () use ($localeId, $key, $content, $tags): Translation {
            $translation = $this->translations->create(
                ['locale_id' => $localeId, 'key' => $key, 'content' => $content],
                $tags,
            );

            $this->locales->bumpExportVersion($localeId);

            return $translation;
        });
    }

    /**
     * @param  array{locale_id?: int, key?: string, content?: string}  $attributes
     * @param  list<string>|null  $tags  Replacement tags, or null to keep the current ones.
     */
    public function update(Translation $translation, array $attributes, ?array $tags = null): Translation
    {
        return DB::transaction(function () use ($translation, $attributes, $tags): Translation {
            $previousLocaleId = $translation->locale_id;

            // The export version changes in the same transaction as the data, so a
            // reader can never observe a new version paired with stale rows.
            if ($this->translations->update($translation, $attributes, $tags)) {
                $this->locales->bumpExportVersion($previousLocaleId, $translation->locale_id);
            }

            return $translation;
        });
    }

    public function delete(Translation $translation): void
    {
        DB::transaction(function () use ($translation): void {
            $this->translations->delete($translation);
            $this->locales->bumpExportVersion($translation->locale_id);
        });
    }
}

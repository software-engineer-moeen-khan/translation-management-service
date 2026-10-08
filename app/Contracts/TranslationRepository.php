<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\TranslationSearchCriteria;
use App\Models\Translation;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface TranslationRepository
{
    /**
     * Find translations matching every given criterion, with locale and tags loaded.
     *
     * @return CursorPaginator<int, Translation>
     */
    public function search(TranslationSearchCriteria $criteria): CursorPaginator;

    /**
     * @param  array{locale_id: int, key: string, content: string}  $attributes
     * @param  list<string>  $tags  Tag names; unknown tags are created.
     */
    public function create(array $attributes, array $tags = []): Translation;

    /**
     * @param  array{locale_id?: int, key?: string, content?: string}  $attributes
     * @param  list<string>|null  $tags  Replacement tag names, or null to leave tags untouched.
     * @return bool Whether anything was actually modified.
     */
    public function update(Translation $translation, array $attributes, ?array $tags = null): bool;

    public function delete(Translation $translation): void;

    /**
     * Every translation of a locale as key => content, optionally limited to
     * translations carrying at least one of the given tags.
     *
     * @param  list<string>  $tags
     * @return array<string, string>
     */
    public function pairsForLocale(int $localeId, array $tags = []): array;
}

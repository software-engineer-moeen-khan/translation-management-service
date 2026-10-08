<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Translation;

interface TranslationRepository
{
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
}

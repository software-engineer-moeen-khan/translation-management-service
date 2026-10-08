<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final readonly class TranslationSearchCriteria
{
    /**
     * @param  string|null  $key  Substring of the translation key.
     * @param  string|null  $content  Text to look for in the translated content.
     * @param  list<string>  $tags  Match translations carrying at least one of these tags.
     * @param  string|null  $locale  Locale code to restrict the search to.
     */
    public function __construct(
        public ?string $key = null,
        public ?string $content = null,
        public array $tags = [],
        public ?string $locale = null,
        public int $perPage = 25,
    ) {
    }
}

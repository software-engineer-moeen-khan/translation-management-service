<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesTagFilter
{
    /**
     * Accept tags as either "tags=web,mobile" or "tags[]=web&tags[]=mobile"
     * and reduce them to a lower-cased list without blanks or duplicates.
     */
    protected function normalizeTagFilter(): void
    {
        $tags = $this->query('tags');

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (! is_array($tags)) {
            return;
        }

        $tags = array_map(fn (mixed $tag): mixed => is_string($tag) ? mb_strtolower(trim($tag)) : $tag, $tags);
        $tags = array_filter($tags, fn (mixed $tag): bool => $tag !== '');

        $this->merge(['tags' => array_values(array_unique($tags, SORT_REGULAR))]);
    }
}

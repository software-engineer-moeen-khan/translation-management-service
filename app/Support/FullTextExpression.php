<?php

declare(strict_types=1);

namespace App\Support;

final class FullTextExpression
{
    /**
     * Build a MySQL boolean-mode expression requiring every word of the term,
     * each matched as a prefix ("sign in" becomes "+sign* +in*").
     *
     * Only letters and digits survive, so user input can never inject
     * boolean-mode operators. Returns null when the term has no words.
     */
    public static function allWordsPrefixed(string $term): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return null;
        }

        return implode(' ', array_map(fn (string $word): string => "+{$word}*", $words));
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Arr;

enum ExportFormat: string
{
    /** {"auth.login.title": "Sign in"} */
    case Flat = 'flat';

    /** {"auth": {"login": {"title": "Sign in"}}} */
    case Nested = 'nested';

    /**
     * Shape key/content pairs the way this format presents them.
     *
     * @param  array<string, string>  $pairs
     * @return array<string, mixed>
     */
    public function shape(array $pairs): array
    {
        if ($this === self::Flat) {
            return $pairs;
        }

        // When a key is also the prefix of another ("menu" and "menu.file") only
        // one can be represented. Sorting puts the prefix first, so the deeper
        // key consistently wins regardless of the order rows were read in.
        ksort($pairs, SORT_STRING);

        return Arr::undot($pairs);
    }
}

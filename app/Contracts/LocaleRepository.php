<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Locale;
use Illuminate\Support\Collection;

interface LocaleRepository
{
    /**
     * @return Collection<int, Locale>
     */
    public function all(): Collection;

    public function create(string $code, string $name): Locale;

    public function findByCode(string $code): ?Locale;

    /**
     * Mark the translations of the given locales as changed, invalidating their exports.
     */
    public function bumpExportVersion(int ...$localeIds): void;
}

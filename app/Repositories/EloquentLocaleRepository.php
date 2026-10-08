<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LocaleRepository;
use App\Models\Locale;
use Illuminate\Support\Collection;

class EloquentLocaleRepository implements LocaleRepository
{
    public function all(): Collection
    {
        return Locale::query()->orderBy('code')->get();
    }

    public function create(string $code, string $name): Locale
    {
        return Locale::query()->create(['code' => $code, 'name' => $name]);
    }

    public function findByCode(string $code): ?Locale
    {
        return Locale::query()->where('code', $code)->first();
    }

    public function bumpExportVersion(int ...$localeIds): void
    {
        if ($localeIds === []) {
            return;
        }

        // Single atomic UPDATE, so concurrent writers never lose an increment.
        Locale::query()->whereKey(array_unique($localeIds))->increment('export_version');
    }
}

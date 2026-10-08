<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TranslationRepository;
use App\Models\Tag;
use App\Models\Translation;

class EloquentTranslationRepository implements TranslationRepository
{
    public function create(array $attributes, array $tags = []): Translation
    {
        $translation = Translation::query()->create($attributes);

        if ($tags !== []) {
            $translation->tags()->attach($this->resolveTagIds($tags));
        }

        return $translation;
    }

    public function update(Translation $translation, array $attributes, ?array $tags = null): bool
    {
        $translation->fill($attributes)->save();
        $changed = $translation->wasChanged();

        if ($tags !== null) {
            $synced = $translation->tags()->sync($this->resolveTagIds($tags));
            $changed = $changed || $synced['attached'] !== [] || $synced['detached'] !== [];
            $translation->unsetRelation('tags');
        }

        if ($translation->wasChanged('locale_id')) {
            $translation->unsetRelation('locale');
        }

        return $changed;
    }

    public function delete(Translation $translation): void
    {
        $translation->delete();
    }

    /**
     * Map tag names to ids, creating the tags that do not exist yet.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function resolveTagIds(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $ids = Tag::query()->whereIn('name', $names)->pluck('id', 'name');
        $missing = array_values(array_diff($names, $ids->keys()->all()));

        if ($missing !== []) {
            $now = now();

            // insertOrIgnore tolerates another request creating the same tag concurrently.
            Tag::query()->insertOrIgnore(array_map(
                fn (string $name): array => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
                $missing,
            ));

            $ids = Tag::query()->whereIn('name', $names)->pluck('id', 'name');
        }

        return $ids->values()->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TranslationRepository;
use App\DataTransferObjects\TranslationSearchCriteria;
use App\Models\Tag;
use App\Models\Translation;
use App\Support\FullTextExpression;
use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class EloquentTranslationRepository implements TranslationRepository
{
    /**
     * Character used to escape LIKE wildcards; unlike a backslash it behaves
     * identically on MySQL, PostgreSQL and SQLite.
     */
    private const LIKE_ESCAPE = '!';

    public function search(TranslationSearchCriteria $criteria): CursorPaginator
    {
        $query = Translation::query()->with(['locale:id,code', 'tags:id,name']);

        if ($criteria->locale !== null) {
            $query->where('locale_id', '=', function (QueryBuilder $locale) use ($criteria): void {
                $locale->select('id')->from('locales')->where('code', $criteria->locale);
            });
        }

        if ($criteria->key !== null) {
            $this->whereContains($query, 'key', $criteria->key);
        }

        if ($criteria->content !== null) {
            $this->whereContentMatches($query, $criteria->content);
        }

        if ($criteria->tags !== []) {
            $query->whereIn('id', $this->taggedWith($criteria->tags));
        }

        // Keyset pagination: page N costs the same as page 1, with no COUNT(*) over the table.
        return $query->orderBy('id')->cursorPaginate($criteria->perPage)->withQueryString();
    }

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

    public function pairsForLocale(int $localeId, array $tags = []): array
    {
        // Query builder instead of Eloquent: an export can cover the whole table
        // and hydrating a model per row would dominate the response time.
        $query = DB::table('translations')->where('locale_id', $localeId);

        if ($tags !== []) {
            $query->whereIn('id', $this->taggedWith($tags));
        }

        return $query->pluck('content', 'key')->all();
    }

    /**
     * Sub-select of the ids of translations carrying at least one of the tags.
     *
     * @param  list<string>  $tags
     * @return Closure(QueryBuilder): void
     */
    private function taggedWith(array $tags): Closure
    {
        return function (QueryBuilder $tagged) use ($tags): void {
            $tagged->select('tag_translation.translation_id')
                ->from('tag_translation')
                ->join('tags', 'tags.id', '=', 'tag_translation.tag_id')
                ->whereIn('tags.name', $tags);
        };
    }

    /**
     * Use the FULLTEXT index where there is one, otherwise fall back to a substring scan.
     *
     * @param  Builder<Translation>  $query
     */
    private function whereContentMatches(Builder $query, string $term): void
    {
        $supportsFullText = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        $expression = $supportsFullText ? FullTextExpression::allWordsPrefixed($term) : null;

        if ($expression === null) {
            $this->whereContains($query, 'content', $term);

            return;
        }

        $query->whereFullText('content', $expression, ['mode' => 'boolean']);
    }

    /**
     * @param  Builder<Translation>  $query
     */
    private function whereContains(Builder $query, string $column, string $term): void
    {
        $escaped = str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $term,
        );

        $query->whereRaw(
            sprintf("%s like ? escape '%s'", $query->getGrammar()->wrap($column), self::LIKE_ESCAPE),
            ["%{$escaped}%"],
        );
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

<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\DataTransferObjects\TranslationSearchCriteria;
use Illuminate\Foundation\Http\FormRequest;

class SearchTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:191'],
            'content' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:12'],
            'tags' => ['nullable', 'array', 'max:' . TranslationRequest::MAX_TAGS],
            'tags.*' => ['string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('translations.pagination.max')],
            'cursor' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function criteria(): TranslationSearchCriteria
    {
        $filters = $this->validated();

        return new TranslationSearchCriteria(
            key: $filters['key'] ?? null,
            content: $filters['content'] ?? null,
            tags: array_values($filters['tags'] ?? []),
            locale: $filters['locale'] ?? null,
            perPage: (int) ($filters['per_page'] ?? config('translations.pagination.default')),
        );
    }

    /**
     * Accept tags as either "tags=web,mobile" or "tags[]=web&tags[]=mobile".
     */
    protected function prepareForValidation(): void
    {
        $tags = $this->query('tags');

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (is_array($tags)) {
            $tags = array_map(fn (mixed $tag): mixed => is_string($tag) ? mb_strtolower(trim($tag)) : $tag, $tags);

            $this->merge([
                'tags' => array_values(array_unique(array_filter($tags, fn (mixed $tag): bool => $tag !== ''), SORT_REGULAR)),
            ]);
        }
    }
}

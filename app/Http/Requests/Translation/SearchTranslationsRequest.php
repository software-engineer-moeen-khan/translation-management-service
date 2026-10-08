<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\DataTransferObjects\TranslationSearchCriteria;
use App\Http\Requests\Concerns\NormalizesTagFilter;
use Illuminate\Foundation\Http\FormRequest;

class SearchTranslationsRequest extends FormRequest
{
    use NormalizesTagFilter;

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

    protected function prepareForValidation(): void
    {
        $this->normalizeTagFilter();
    }
}

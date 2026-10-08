<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\Enums\ExportFormat;
use App\Http\Requests\Concerns\NormalizesTagFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportTranslationsRequest extends FormRequest
{
    use NormalizesTagFilter;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::enum(ExportFormat::class)],
            'tags' => ['nullable', 'array', 'max:' . TranslationRequest::MAX_TAGS],
            'tags.*' => ['string', 'max:64'],
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::tryFrom((string) $this->validated('format')) ?? ExportFormat::Flat;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return array_values($this->validated('tags') ?? []);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeTagFilter();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use Illuminate\Validation\Rule;

class StoreTranslationRequest extends TranslationRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', ...$this->localeRules()],
            'key' => [
                'required',
                ...$this->keyRules(),
                Rule::unique('translations', 'key')->where('locale_id', $this->locale()?->id),
            ],
            'content' => ['required', ...$this->contentRules()],
            ...$this->tagRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.unique' => 'The key already exists for this locale.',
        ];
    }
}

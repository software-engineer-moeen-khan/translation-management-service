<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\Models\Translation;
use Illuminate\Validation\Validator;

class UpdateTranslationRequest extends TranslationRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'required', ...$this->localeRules()],
            'key' => ['sometimes', 'required', ...$this->keyRules()],
            'content' => ['sometimes', 'required', ...$this->contentRules()],
            ...$this->tagRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Translation $translation */
                $translation = $this->route('translation');
                $localeId = $this->has('locale') ? $this->locale()->id : $translation->locale_id;
                $key = $this->input('key', $translation->key);

                // Uniqueness depends on the (locale, key) pair, and either half may be changing.
                if ($localeId === $translation->locale_id && $key === $translation->key) {
                    return;
                }

                $taken = Translation::query()
                    ->where('locale_id', $localeId)
                    ->where('key', $key)
                    ->whereKeyNot($translation->id)
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('key', 'The key already exists for this locale.');
                }
            },
        ];
    }

    /**
     * Column values to change, limited to what the client actually sent.
     *
     * @return array{locale_id?: int, key?: string, content?: string}
     */
    public function changes(): array
    {
        $changes = $this->safe()->only(['key', 'content']);

        if ($this->has('locale')) {
            $changes['locale_id'] = $this->locale()->id;
        }

        return $changes;
    }
}

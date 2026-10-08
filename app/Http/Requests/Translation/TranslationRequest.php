<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\Contracts\LocaleRepository;
use App\Models\Locale;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

abstract class TranslationRequest extends FormRequest
{
    /**
     * Dot-separated segments, e.g. "checkout.summary.total".
     */
    public const KEY_PATTERN = '/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/';

    public const TAG_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    public const MAX_TAGS = 20;

    private Locale|false|null $resolvedLocale = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The locale referenced by the "locale" input, looked up at most once per request.
     */
    public function locale(): ?Locale
    {
        if ($this->resolvedLocale === false) {
            $code = $this->input('locale');

            $this->resolvedLocale = is_string($code)
                ? $this->container->make(LocaleRepository::class)->findByCode($code)
                : null;
        }

        return $this->resolvedLocale;
    }

    /**
     * Tag names from the validated payload, or null when tags were not sent.
     *
     * @return list<string>|null
     */
    public function tags(): ?array
    {
        $tags = $this->validated('tags');

        return $tags === null ? null : array_values($tags);
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_array($tags)) {
            $this->merge([
                'tags' => array_map(fn (mixed $tag): mixed => is_string($tag) ? mb_strtolower($tag) : $tag, $tags),
            ]);
        }
    }

    /**
     * @return list<mixed>
     */
    protected function localeRules(): array
    {
        return [
            'string',
            'max:12',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->locale() === null) {
                    $fail('The selected locale does not exist.');
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    protected function keyRules(): array
    {
        return ['string', 'max:191', 'regex:' . self::KEY_PATTERN];
    }

    /**
     * @return list<string>
     */
    protected function contentRules(): array
    {
        return ['string', 'max:10000'];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function tagRules(): array
    {
        return [
            'tags' => ['array', 'max:' . self::MAX_TAGS],
            'tags.*' => ['string', 'max:64', 'distinct', 'regex:' . self::TAG_PATTERN],
        ];
    }
}

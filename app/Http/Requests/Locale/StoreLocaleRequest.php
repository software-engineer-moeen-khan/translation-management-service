<?php

declare(strict_types=1);

namespace App\Http\Requests\Locale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocaleRequest extends FormRequest
{
    /**
     * Language tag such as "en", "pt-BR" or "zh-Hans-CN".
     */
    public const CODE_PATTERN = '/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/';

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
            'code' => ['required', 'string', 'max:12', 'regex:' . self::CODE_PATTERN, Rule::unique('locales', 'code')],
            'name' => ['required', 'string', 'max:64'],
        ];
    }
}

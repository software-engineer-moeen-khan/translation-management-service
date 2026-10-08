<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $locale_id
 * @property string $key
 * @property string $content
 */
#[Fillable(['locale_id', 'key', 'content'])]
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Locale, $this>
     */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale_id' => 'integer',
        ];
    }
}

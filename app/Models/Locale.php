<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LocaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $export_version
 */
#[Fillable(['code', 'name'])]
class Locale extends Model
{
    /** @use HasFactory<LocaleFactory> */
    use HasFactory;

    /**
     * @return HasMany<Translation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'export_version' => 'integer',
        ];
    }
}

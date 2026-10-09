<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\LocaleRepository;
use App\Models\Locale;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedTranslationsCommand extends Command
{
    protected $signature = 'translations:seed
        {--count=100000 : Number of translations to generate}
        {--locales=en,fr,es : Comma separated locale codes to spread the translations over}
        {--chunk=1000 : Rows per INSERT statement}';

    protected $description = 'Populate the database with a large number of translations for load testing';

    private const MAX_CHUNK = 5000;

    private const LOCALE_NAMES = [
        'en' => 'English',
        'fr' => 'French',
        'es' => 'Spanish',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
    ];

    private const TAGS = ['mobile', 'desktop', 'web'];

    private const GROUPS = ['auth', 'checkout', 'dashboard', 'profile', 'settings', 'billing', 'errors', 'onboarding'];

    private const SECTIONS = ['title', 'subtitle', 'label', 'button', 'hint', 'message', 'placeholder', 'tooltip'];

    private const WORDS = [
        'welcome', 'please', 'confirm', 'your', 'account', 'order', 'payment', 'details', 'continue', 'cancel',
        'save', 'changes', 'successfully', 'updated', 'invalid', 'required', 'field', 'password', 'email', 'address',
        'shipping', 'total', 'summary', 'settings', 'language', 'notifications', 'profile', 'search', 'results', 'empty',
    ];

    public function handle(LocaleRepository $locales): int
    {
        $count = (int) $this->option('count');
        $chunk = (int) $this->option('chunk');
        $codes = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('locales'))))));

        if ($count < 1 || $chunk < 1 || $chunk > self::MAX_CHUNK || $codes === []) {
            $this->error(sprintf(
                'Expected --count >= 1, --chunk between 1 and %d and at least one locale.',
                self::MAX_CHUNK,
            ));

            return self::INVALID;
        }

        $startedAt = microtime(true);
        $localeIds = $this->ensureLocales($codes);
        $tagIds = $this->ensureTags();

        // New keys are numbered after the current highest id, so the command can
        // be run repeatedly without colliding with rows from an earlier run.
        $firstId = (int) DB::table('translations')->max('id');

        $progress = $this->output->createProgressBar($count);

        for ($offset = 0; $offset < $count; $offset += $chunk) {
            $rows = $this->rows($firstId + $offset, min($chunk, $count - $offset), $localeIds);

            // Plain multi-row INSERTs: no models, events or per-row round trips.
            DB::table('translations')->insert($rows);
            $progress->advance(count($rows));
        }

        $progress->finish();
        $this->newLine();

        $this->tag($firstId, $tagIds);
        $locales->bumpExportVersion(...$localeIds);

        $this->info(sprintf(
            'Seeded %s translations across %d locale(s) in %.2fs.',
            number_format($count),
            count($localeIds),
            microtime(true) - $startedAt,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $codes
     * @return list<int>
     */
    private function ensureLocales(array $codes): array
    {
        return array_map(
            fn (string $code): int => Locale::query()
                ->firstOrCreate(['code' => $code], ['name' => self::LOCALE_NAMES[$code] ?? strtoupper($code)])
                ->id,
            $codes,
        );
    }

    /**
     * @return list<int>
     */
    private function ensureTags(): array
    {
        return array_map(
            fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id,
            self::TAGS,
        );
    }

    /**
     * @param  list<int>  $localeIds
     * @return list<array<string, mixed>>
     */
    private function rows(int $sequence, int $size, array $localeIds): array
    {
        $now = now()->toDateTimeString();
        $localeCount = count($localeIds);
        $rows = [];

        for ($i = 0; $i < $size; $i++) {
            $n = $sequence + $i;

            $rows[] = [
                'locale_id' => $localeIds[$n % $localeCount],
                'key' => sprintf(
                    '%s.%s.item_%d',
                    self::GROUPS[$n % count(self::GROUPS)],
                    self::SECTIONS[intdiv($n, count(self::GROUPS)) % count(self::SECTIONS)],
                    $n,
                ),
                'content' => $this->sentence($n),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * A deterministic pseudo-sentence, so repeated runs produce comparable data.
     */
    private function sentence(int $n): string
    {
        $wordCount = count(self::WORDS);
        $words = [];

        for ($i = 0, $length = 3 + $n % 5; $i < $length; $i++) {
            $words[] = self::WORDS[($n * 7 + $i * 13) % $wordCount];
        }

        return ucfirst(implode(' ', $words)) . " {$n}";
    }

    /**
     * Tag the new rows with set-based INSERT ... SELECT statements: every row
     * gets one tag and roughly a quarter get a second one.
     *
     * Buckets are taken modulo primes so that tags stay independent of the
     * locale a row landed in (which is assigned round-robin).
     *
     * @param  list<int>  $tagIds
     */
    private function tag(int $firstId, array $tagIds): void
    {
        [$mobile, $desktop, $web] = $tagIds;

        $this->attachTag($mobile, $firstId, 'id % 11 between ? and ?', [0, 3]);
        $this->attachTag($desktop, $firstId, 'id % 11 between ? and ?', [4, 7]);
        $this->attachTag($web, $firstId, 'id % 11 between ? and ?', [8, 10]);

        foreach ($tagIds as $index => $tagId) {
            $this->attachTag($tagId, $firstId, 'id % 13 = ?', [$index]);
        }
    }

    /**
     * @param  list<int>  $bindings
     */
    private function attachTag(int $tagId, int $firstId, string $condition, array $bindings): void
    {
        DB::table('tag_translation')->insertOrIgnoreUsing(
            ['translation_id', 'tag_id'],
            DB::table('translations')
                ->selectRaw('id, ?', [$tagId])
                ->where('id', '>', $firstId)
                ->whereRaw($condition, $bindings),
        );
    }
}

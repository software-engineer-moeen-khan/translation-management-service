<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $isMySql = in_array($driver, ['mysql', 'mariadb'], true);

        Schema::create('translations', function (Blueprint $table) use ($isMySql): void {
            $table->id();
            $table->foreignId('locale_id')->constrained()->restrictOnDelete();

            // Keys are identifiers consumed by frontends, so they must compare case-sensitively.
            $key = $table->string('key', 191);
            if ($isMySql) {
                $key->collation('utf8mb4_bin');
            }

            $table->text('content');
            $table->timestamps();

            // Serves the uniqueness check and the per-locale export scan.
            $table->unique(['locale_id', 'key']);

            // Content search is word based where the engine supports it.
            if ($isMySql) {
                $table->fullText('content');
            }
        });

        Schema::create('tag_translation', function (Blueprint $table): void {
            $table->foreignId('translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['translation_id', 'tag_id']);
            // Reverse lookup: "translations carrying tag X".
            $table->index(['tag_id', 'translation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_translation');
        Schema::dropIfExists('translations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 64);
            // Bumped on every change to the locale's translations; drives export caching.
            $table->unsignedBigInteger('export_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};

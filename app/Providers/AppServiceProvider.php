<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\LocaleRepository;
use App\Contracts\TranslationRepository;
use App\Repositories\EloquentLocaleRepository;
use App\Repositories\EloquentTranslationRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Contracts and the implementations the application depends on.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        LocaleRepository::class => EloquentLocaleRepository::class,
        TranslationRepository::class => EloquentTranslationRepository::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(config('translations.rate_limits.api'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(config('translations.rate_limits.login'))
                ->by(Str::lower((string) $request->input('email')) . '|' . $request->ip());
        });
    }
}

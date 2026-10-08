<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LocaleController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('locales', [LocaleController::class, 'index'])->name('locales.index');
        Route::post('locales', [LocaleController::class, 'store'])->name('locales.store');

        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    });
});

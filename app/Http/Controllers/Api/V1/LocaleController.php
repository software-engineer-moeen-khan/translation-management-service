<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locale\StoreLocaleRequest;
use App\Http\Resources\LocaleResource;
use App\Models\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocaleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LocaleResource::collection(
            Locale::query()->orderBy('code')->get(['id', 'code', 'name']),
        );
    }

    public function store(StoreLocaleRequest $request): JsonResponse
    {
        $locale = Locale::query()->create($request->validated());

        return LocaleResource::make($locale)->response()->setStatusCode(201);
    }
}

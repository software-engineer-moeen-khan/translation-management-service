<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\LocaleRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locale\StoreLocaleRequest;
use App\Http\Resources\LocaleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocaleController extends Controller
{
    public function __construct(private readonly LocaleRepository $locales)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return LocaleResource::collection($this->locales->all());
    }

    public function store(StoreLocaleRequest $request): JsonResponse
    {
        $locale = $this->locales->create($request->validated('code'), $request->validated('name'));

        return LocaleResource::make($locale)->response()->setStatusCode(201);
    }
}

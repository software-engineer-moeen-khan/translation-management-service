<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Translation\StoreTranslationRequest;
use App\Http\Requests\Translation\UpdateTranslationRequest;
use App\Http\Resources\TranslationResource;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TranslationController extends Controller
{
    /**
     * Relations (and columns) the resource needs.
     */
    private const RELATIONS = ['locale:id,code', 'tags:id,name'];

    public function __construct(private readonly TranslationService $translations)
    {
    }

    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $translation = $this->translations->create(
            $request->locale()->id,
            $request->validated('key'),
            $request->validated('content'),
            $request->tags() ?? [],
        );

        return TranslationResource::make($translation->load(self::RELATIONS))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.translations.show', $translation));
    }

    public function show(Translation $translation): TranslationResource
    {
        return TranslationResource::make($translation->load(self::RELATIONS));
    }

    public function update(UpdateTranslationRequest $request, Translation $translation): TranslationResource
    {
        $translation = $this->translations->update($translation, $request->changes(), $request->tags());

        return TranslationResource::make($translation->load(self::RELATIONS));
    }

    public function destroy(Translation $translation): Response
    {
        $this->translations->delete($translation);

        return response()->noContent();
    }
}

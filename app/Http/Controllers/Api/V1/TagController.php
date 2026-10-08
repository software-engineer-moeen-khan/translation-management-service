<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    private const PER_PAGE = 100;

    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::query()->orderBy('name')->cursorPaginate(self::PER_PAGE, ['id', 'name']),
        );
    }
}

<?php

namespace App\Http\Controllers\Api\Trading;

use App\Http\Controllers\Api\ApiController;
use App\Services\MarketCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketFavoriteController extends ApiController
{
    public function __construct(protected MarketCatalogService $catalog) {}

    public function toggle(Request $request, int $assetId): JsonResponse
    {
        $isFavorited = $this->catalog->toggleFavorite($request->user(), $assetId);

        return $this->success(['is_favorited' => $isFavorited]);
    }
}

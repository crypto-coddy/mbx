<?php

namespace App\Http\Controllers\Api\Trading;

use App\Http\Controllers\Api\ApiController;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

class AssetController extends ApiController
{
    public function index(): JsonResponse
    {
        $assets = Asset::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Asset $asset) => $this->formatAsset($asset));

        return $this->success($assets);
    }

    public function show(string $symbol): JsonResponse
    {
        $asset = Asset::where('symbol', strtoupper($symbol))
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success($this->formatAsset($asset));
    }

    protected function formatAsset(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'symbol' => $asset->symbol,
            'category' => $asset->category ?? 'commodities',
            'display_name' => $asset->display_name,
            'icon_url' => $asset->icon_url,
            'currency' => $asset->currency,
            'live_price' => $asset->live_price,
            'effective_price' => $asset->effectivePrice(),
            'price_source' => $asset->priceSource(),
            'price_change_24h' => $asset->price_change_24h,
            'price_updated_at' => $asset->price_updated_at,
            'trading_enabled' => $asset->trading_enabled,
            'min_trade_amount' => $asset->min_trade_amount,
            'max_trade_amount' => $asset->max_trade_amount,
        ];
    }
}

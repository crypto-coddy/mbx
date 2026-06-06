<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\PriceUpdated;
use App\Http\Controllers\Api\ApiController;
use App\Jobs\FetchLivePricesJob;
use App\Models\Asset;
use App\Models\PriceHistory;
use App\Services\AdminActivityLogger;
use App\Services\MarketChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceManagementController extends ApiController
{
    public function __construct(
        protected AdminActivityLogger $activityLogger,
        protected MarketChartService $charts,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(Asset::orderBy('sort_order')->get());
    }

    public function setOverride(Request $request, int $assetId): JsonResponse
    {
        $data = $request->validate(['price' => ['required', 'numeric', 'min:0']]);
        $asset = Asset::findOrFail($assetId);

        $asset->update([
            'admin_price' => $data['price'],
            'admin_override_active' => true,
            'override_set_by' => $request->user()->id,
            'override_set_at' => now(),
        ]);

        PriceHistory::create([
            'asset_id' => $asset->id,
            'price' => $data['price'],
            'source' => 'admin_override',
            'interval' => '1m',
            'recorded_at' => now(),
        ]);

        event(new PriceUpdated($asset->fresh(), (string) $data['price'], 'admin_override'));

        $this->activityLogger->log(
            $request->user()->id,
            'price.override',
            "Set admin price override for {$asset->symbol}",
            null,
            ['price' => $data['price']],
            $asset,
            $request
        );

        return $this->success($asset->fresh(), 'Price override set.');
    }

    public function removeOverride(Request $request, int $assetId): JsonResponse
    {
        $asset = Asset::findOrFail($assetId);

        $asset->update([
            'admin_price' => null,
            'admin_override_active' => false,
            'override_set_by' => null,
            'override_set_at' => null,
        ]);

        $this->activityLogger->log(
            $request->user()->id,
            'price.override_removed',
            "Removed price override for {$asset->symbol}",
            null,
            null,
            $asset,
            $request
        );

        return $this->success($asset->fresh(), 'Price override removed.');
    }

    public function forceRefresh(): JsonResponse
    {
        FetchLivePricesJob::dispatchSync();

        return $this->success(null, 'Prices refreshed.');
    }

    public function setChartTrend(Request $request, int $assetId): JsonResponse
    {
        $data = $request->validate([
            'trend' => ['required', 'in:up,down'],
        ]);

        $asset = Asset::findOrFail($assetId);
        $asset = $this->charts->setTrend($asset, $data['trend'], $request->user()->id);

        $this->activityLogger->log(
            $request->user()->id,
            'chart.trend',
            "Set {$asset->symbol} chart trend to {$data['trend']}",
            null,
            ['trend' => $data['trend']],
            $asset,
            $request
        );

        return $this->success(
            app(MarketChartService::class)->formatAssetForApi($asset),
            $data['trend'] === 'up'
                ? 'Chart set to UP — users see buy signal.'
                : 'Chart set to DOWN — users see hold / don\'t buy signal.'
        );
    }
}

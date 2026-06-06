<?php

namespace App\Http\Controllers\Api\Trading;

use App\Http\Controllers\Api\ApiController;
use App\Models\Asset;
use App\Models\PriceHistory;
use App\Services\ChartDataModeService;
use App\Services\MarketCatalogService;
use App\Services\MarketChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class PriceController extends ApiController
{
    public function __construct(
        protected MarketChartService $charts,
        protected MarketCatalogService $catalog,
        protected ChartDataModeService $chartMode,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['sometimes', 'string', 'max:32'],
            'search' => ['sometimes', 'string', 'max:120'],
            'favorites_only' => ['sometimes', 'boolean'],
        ]);

        $user = $this->resolveUser($request);
        $profile = $user?->profile;

        $assets = $this->catalog->listForUser(
            $user,
            $data['category'] ?? null,
            $data['search'] ?? null,
            (bool) ($data['favorites_only'] ?? false),
        )->values();

        // `data` is a flat array so older mobile clients (FlatList) keep working.
        return response()->json([
            'success' => true,
            'data' => $assets,
            'meta' => [
                'categories' => MarketCatalogService::CATEGORIES,
                'chart_data_mode' => $this->chartMode->modeForProfile($profile),
                'account_label' => $this->chartMode->isRealForProfile($profile) ? 'Real' : 'Custom',
            ],
            'message' => 'OK',
        ]);
    }

    public function history(Request $request, string $symbol): JsonResponse
    {
        $data = $request->validate([
            'interval' => ['sometimes', 'in:1m,5m,15m,1h,4h,1d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $asset = Asset::where('symbol', strtoupper($symbol))->firstOrFail();
        $profile = $this->resolveProfile($request);
        $profileTrends = $this->charts->chartTrendsForProfile($profile);
        $trend = $this->charts->resolveTrend($asset, $profile, $profileTrends);

        $history = PriceHistory::where('asset_id', $asset->id)
            ->when(isset($data['interval']), fn ($q) => $q->where('interval', $data['interval']))
            ->orderByDesc('recorded_at')
            ->limit($data['limit'] ?? 100)
            ->get()
            ->reverse()
            ->values();

        if ($history->count() < 10) {
            $history = collect($this->charts->getChartForAsset($asset, $data['limit'] ?? 40, $trend));
        }

        return $this->success([
            'asset' => $this->charts->formatAssetForApi($asset, $profile, $profileTrends),
            'history' => $history,
        ]);
    }

    protected function resolveUser(Request $request): ?\App\Models\User
    {
        $user = $request->user();

        if (! $user && $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($request->bearerToken());
            $user = $token?->tokenable;
        }

        return $user;
    }

    protected function resolveProfile(Request $request): ?\App\Models\UserProfile
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            return null;
        }

        return $user->profile()->firstOrCreate(['user_id' => $user->id], ['country' => 'India']);
    }
}

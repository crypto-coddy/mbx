<?php

namespace App\Http\Controllers\Api\Trading;

use App\Http\Controllers\Api\ApiController;
use App\Models\Asset;
use App\Models\PriceHistory;
use App\Models\UserProfile;
use App\Services\ChartDataModeService;
use App\Services\MarketCatalogService;
use App\Services\MarketChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $category = $data['category'] ?? null;
        $search = $data['search'] ?? null;
        $favoritesOnly = (bool) ($data['favorites_only'] ?? false);
        $chartMode = $this->chartMode->modeForProfile($profile);

        $this->maybeAdvanceStaleQuotes($profile, $chartMode);

        // Short bucket so quotes refresh frequently for live charts.
        $timeBucket = (int) floor(time() / 2);
        $cacheKey = 'api.prices.'.($user?->id ?? 'guest').'.'.$timeBucket.'.'.md5(json_encode([
            'category' => $category,
            'search' => $search,
            'favorites_only' => $favoritesOnly,
            'chart_mode' => $chartMode,
        ]));

        $assets = Cache::remember($cacheKey, now()->addSeconds(2), fn () => $this->catalog->listForUser(
            $user,
            $category,
            $search,
            $favoritesOnly,
        )->values());

        // `data` is a flat array so older mobile clients (FlatList) keep working.
        return response()->json([
            'success' => true,
            'data' => $assets,
            'meta' => [
                'categories' => MarketCatalogService::CATEGORIES,
                'chart_data_mode' => $chartMode,
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

    protected function maybeAdvanceStaleQuotes(?UserProfile $profile, string $chartMode): void
    {
        if (app()->environment('testing') || $chartMode !== ChartDataModeService::MODE_CUSTOM) {
            return;
        }

        if (! Cache::add('api.prices.advance_lock', 1, 5)) {
            return;
        }

        $this->charts->tickAllCustomCharts();
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

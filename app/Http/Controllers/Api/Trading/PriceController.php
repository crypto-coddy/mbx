<?php

namespace App\Http\Controllers\Api\Trading;

use App\Http\Controllers\Api\ApiController;
use App\Models\Asset;
use App\Models\PriceHistory;
use App\Models\UserProfile;
use App\Services\ChartDataModeService;
use App\Services\ChartDataVersionService;
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
        protected ChartDataVersionService $chartVersion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['sometimes', 'string', 'max:32'],
            'search' => ['sometimes', 'string', 'max:120'],
            'favorites_only' => ['sometimes', 'boolean'],
        ]);

        $user = $this->resolveUser($request);
        if ($user) {
            $user->load('profile');
        }
        $profile = $user?->profile;

        $category = $data['category'] ?? null;
        $search = $data['search'] ?? null;
        $favoritesOnly = (bool) ($data['favorites_only'] ?? false);
        $chartMode = $this->chartMode->modeForProfile($profile);
        $chartVersion = $this->chartVersion->versionForProfile($profile);
        $chartMeta = $this->chartVersion->mobileMetaForProfile($profile);

        // Throttled custom-mode ticks so mobile polls see movement without overshooting.
        $this->maybeAdvanceStaleQuotes($profile, $chartMode);

        // Match mobile poll interval (5s) so each poll can see fresh quotes.
        $timeBucket = (int) floor(time() / 5);
        $cacheKey = 'api.prices.'.($user?->id ?? 'guest').'.'.$timeBucket.'.'.md5(json_encode([
            'category' => $category,
            'search' => $search,
            'favorites_only' => $favoritesOnly,
            'chart_mode' => $chartMode,
            'chart_version' => $chartVersion,
        ]));

        $assets = Cache::remember($cacheKey, now()->addSeconds(5), fn () => $this->catalog->listForUser(
            $user,
            $category,
            $search,
            $favoritesOnly,
            lite: true,
        )->values());

        // `data` is a flat array so older mobile clients (FlatList) keep working.
        return response()->json([
            'success' => true,
            'data' => $assets,
            'meta' => [
                'categories' => MarketCatalogService::CATEGORIES,
                'chart_data_mode' => $chartMode,
                'chart_data_version' => $chartVersion,
                'chart_config_scope' => $chartMeta['scope'],
                'chart_source_override' => $chartMeta['source_override'],
                'chart_version_override' => $chartMeta['version_override'],
            ],
            'message' => 'OK',
        ]);
    }

    public function show(Request $request, string $symbol): JsonResponse
    {
        $asset = Asset::query()
            ->where('symbol', strtoupper($symbol))
            ->where('is_active', true)
            ->firstOrFail();

        $user = $this->resolveUser($request);
        if ($user) {
            $user->load('profile');
        }
        $profile = $user?->profile;
        $profileTrends = $this->charts->chartTrendsForProfile($profile);
        $chartMeta = $this->chartVersion->mobileMetaForProfile($profile);
        $payload = $this->charts->formatAssetForApi($asset, $profile, $profileTrends, lite: false);
        $payload['is_favorited'] = $user
            ? $this->catalog->isFavorited($user, $asset->id)
            : false;

        return response()->json([
            'success' => true,
            'data' => $payload,
            'meta' => [
                'chart_data_mode' => $chartMeta['mode'],
                'chart_data_version' => $chartMeta['version'],
                'chart_config_scope' => $chartMeta['scope'],
                'chart_source_override' => $chartMeta['source_override'],
                'chart_version_override' => $chartMeta['version_override'],
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

        if (! Cache::add('api.prices.advance_lock', 1, 20)) {
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

<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use App\Models\UserAssetFavorite;
use App\Support\MarketAssetCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketCatalogService
{
    public const CATEGORIES = [
        'commodities' => 'Commodities',
        'crypto' => 'Crypto',
        'forex' => 'Forex',
        'indices' => 'Indices',
    ];

    public function __construct(protected MarketChartService $charts) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(
        ?User $user,
        ?string $category = null,
        ?string $search = null,
        bool $favoritesOnly = false,
        bool $lite = false,
    ): Collection {
        $this->maybeEnsureDefaultCatalog();

        $profile = $user?->profile;
        $profileTrends = $profile ? $this->charts->chartTrendsForProfile($profile) : [];

        $favoriteIds = $user
            ? UserAssetFavorite::where('user_id', $user->id)->pluck('asset_id')->all()
            : [];

        $query = Asset::query()
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($category && $category !== 'all') {
            if ($category === 'favorites') {
                $query->whereIn('id', $favoriteIds ?: [0]);
            } else {
                $query->where('category', $category);
            }
        }

        if ($favoritesOnly) {
            $query->whereIn('id', $favoriteIds ?: [0]);
        }

        if ($search) {
            $term = '%'.strtolower($search).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(display_name) LIKE ?', [$term]);
            });
        }

        $assets = $query->get();

        if ($assets->isEmpty() && ! $search && ! $category && ! $favoritesOnly) {
            $assets = Asset::orderBy('sort_order')->get();
        }

        $this->charts->preloadRealChartsForProfile($profile, $assets, $lite);

        return $assets->map(function (Asset $asset) use ($profile, $profileTrends, $favoriteIds, $lite) {
            $payload = $this->charts->formatAssetForApi($asset, $profile, $profileTrends, $lite);
            $payload['category'] = $asset->category ?? 'commodities';
            $payload['icon_url'] = $asset->icon_url;
            $payload['currency'] = $asset->currency;
            $payload['is_favorited'] = in_array($asset->id, $favoriteIds, true);

            return $payload;
        });
    }

    public function toggleFavorite(User $user, int $assetId): bool
    {
        Asset::where('id', $assetId)->where('is_active', true)->firstOrFail();

        $existing = UserAssetFavorite::where('user_id', $user->id)
            ->where('asset_id', $assetId)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        UserAssetFavorite::create([
            'user_id' => $user->id,
            'asset_id' => $assetId,
        ]);

        return true;
    }

    public function isFavorited(User $user, int $assetId): bool
    {
        return UserAssetFavorite::where('user_id', $user->id)
            ->where('asset_id', $assetId)
            ->exists();
    }

    /** Keep catalog in sync — adds missing instruments after migrate or partial seed. */
    public function ensureDefaultCatalog(): void
    {
        $changed = false;

        foreach (MarketAssetCatalog::definitions() as $data) {
            $symbol = $data['symbol'];
            $existing = Asset::where('symbol', $symbol)->first();

            if ($existing) {
                $existing->update([
                    'name' => $data['name'],
                    'display_name' => $data['display_name'],
                    'category' => $data['category'],
                    'sort_order' => $data['sort_order'],
                    'api_config' => $data['api_config'] ?? null,
                    'is_active' => true,
                    'trading_enabled' => true,
                ]);
                continue;
            }

            Asset::create(array_merge($data, [
                'is_active' => true,
                'trading_enabled' => true,
                'chart_trend' => $data['chart_trend'] ?? 'up',
                'price_updated_at' => now(),
            ]));
            $changed = true;
        }

        if ($changed) {
            $this->charts->seedAllActiveAssets();
        }
    }

    /** Skip catalog upsert in tests when assets were seeded manually. */
    protected function maybeEnsureDefaultCatalog(): void
    {
        if (Asset::count() > 0) {
            return;
        }

        $this->ensureDefaultCatalog();
    }
}

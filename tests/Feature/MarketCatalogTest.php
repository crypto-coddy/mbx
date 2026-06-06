<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAssetFavorite;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketCatalogTest extends TestCase
{
    public function test_prices_can_be_filtered_by_category(): void
    {
        $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'category' => 'commodities',
            'live_price' => 2600,
        ]);

        $this->testAsset([
            'name' => 'Tether',
            'symbol' => 'USDT',
            'display_name' => 'USDT',
            'category' => 'crypto',
            'live_price' => 1,
        ]);

        $response = $this->getJson('/api/v1/prices?category=crypto')->assertOk();
        $items = collect($response->json('data'));
        $this->assertTrue($items->contains('symbol', 'USDT'));
        $this->assertTrue($items->every(fn (array $row) => $row['category'] === 'crypto'));
    }

    public function test_user_can_toggle_favorite(): void
    {
        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'category' => 'commodities',
            'live_price' => 2600,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/markets/favorites/{$asset->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true);

        $this->assertDatabaseHas('user_asset_favorites', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
        ]);

        $this->postJson("/api/v1/markets/favorites/{$asset->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false);

        $this->assertDatabaseMissing('user_asset_favorites', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
        ]);
    }

    public function test_favorites_filter_returns_only_starred_assets(): void
    {
        $gold = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'category' => 'commodities',
            'live_price' => 2600,
        ]);

        $this->testAsset([
            'name' => 'Silver',
            'symbol' => 'XAG',
            'display_name' => 'Silver',
            'category' => 'commodities',
            'live_price' => 30,
        ]);

        $user = User::factory()->create();
        UserAssetFavorite::create(['user_id' => $user->id, 'asset_id' => $gold->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/prices?category=favorites')->assertOk();
        $items = collect($response->json('data'));
        $this->assertTrue($items->contains('symbol', 'XAU'));
        $this->assertTrue($items->every(fn (array $row) => $row['is_favorited'] === true));
    }
}

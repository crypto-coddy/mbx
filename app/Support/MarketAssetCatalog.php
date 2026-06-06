<?php

namespace App\Support;

/**
 * Default markets shown on mobile — one or more assets per category.
 * Admin can edit category, prices, and chart trend in Filament → Markets.
 */
class MarketAssetCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            // Commodities — popularity order matches Octa "All" list
            [
                'name' => 'Gold',
                'symbol' => 'XAU',
                'display_name' => 'Gold (XAU/USD)',
                'category' => 'commodities',
                'live_price' => 4467.61,
                'price_change_24h' => -1.59,
                'api_config' => ['provider' => 'metals_api', 'symbol' => 'XAU', 'base' => 'USD'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Silver',
                'symbol' => 'XAG',
                'display_name' => 'Silver (XAG/USD)',
                'category' => 'commodities',
                'live_price' => 31,
                'price_change_24h' => 0.85,
                'api_config' => ['provider' => 'metals_api', 'symbol' => 'XAG', 'base' => 'USD'],
                'sort_order' => 9,
            ],
            [
                'name' => 'Crude Oil',
                'symbol' => 'WTI',
                'display_name' => 'WTI Crude Oil',
                'category' => 'commodities',
                'live_price' => 78.5,
                'price_change_24h' => -0.42,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => 'CL=F'],
                'sort_order' => 10,
            ],
            // Crypto
            [
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'display_name' => 'Bitcoin (BTC/USD)',
                'category' => 'crypto',
                'live_price' => 72868.00,
                'price_change_24h' => -2.70,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => 'BTC-USD'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'display_name' => 'Ethereum (ETH/USD)',
                'category' => 'crypto',
                'live_price' => 2145.30,
                'price_change_24h' => -1.52,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => 'ETH-USD'],
                'sort_order' => 4,
            ],
            [
                'name' => 'Tether',
                'symbol' => 'USDT',
                'display_name' => 'Tether (USDT/USD)',
                'category' => 'crypto',
                'live_price' => 1,
                'price_change_24h' => 0.01,
                'api_config' => ['provider' => 'binance', 'pair' => 'USDCUSDT'],
                'sort_order' => 12,
            ],
            // Forex
            [
                'name' => 'EUR / USD',
                'symbol' => 'EURUSD',
                'display_name' => 'Euro vs US Dollar',
                'category' => 'forex',
                'live_price' => 1.08542,
                'price_change_24h' => -0.24,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => 'EURUSD=X'],
                'sort_order' => 3,
            ],
            [
                'name' => 'GBP / USD',
                'symbol' => 'GBPUSD',
                'display_name' => 'British Pound vs US Dollar',
                'category' => 'forex',
                'live_price' => 1.26538,
                'price_change_24h' => -0.19,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => 'GBPUSD=X'],
                'sort_order' => 6,
            ],
            [
                'name' => 'USD / JPY',
                'symbol' => 'USDJPY',
                'display_name' => 'US Dollar vs Japanese Yen',
                'category' => 'forex',
                'live_price' => 156.245,
                'price_change_24h' => 0.22,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => 'USDJPY=X'],
                'sort_order' => 5,
            ],
            // Indices
            [
                'name' => 'S&P 500',
                'symbol' => 'SPX',
                'display_name' => 'S&P 500 Index',
                'category' => 'indices',
                'live_price' => 5900,
                'price_change_24h' => 0.45,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => '^GSPC'],
                'sort_order' => 30,
            ],
            [
                'name' => 'NASDAQ 100',
                'symbol' => 'NAS100',
                'display_name' => 'NASDAQ 100',
                'category' => 'indices',
                'live_price' => 21452.30,
                'price_change_24h' => -0.18,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => '^NDX'],
                'sort_order' => 7,
            ],
            [
                'name' => 'Dow Jones',
                'symbol' => 'US30',
                'display_name' => 'Dow Jones Industrial',
                'category' => 'indices',
                'live_price' => 42512.80,
                'price_change_24h' => -0.28,
                'api_config' => ['provider' => 'yahoo', 'yahoo_symbol' => '^DJI'],
                'sort_order' => 8,
            ],
        ];
    }
}

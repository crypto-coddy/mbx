<?php

namespace App\Jobs;

use App\Events\PriceUpdated;
use App\Models\Asset;
use App\Models\PriceHistory;
use App\Services\ChartDataModeService;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchLivePricesJob
{
    use Queueable;

    public function handle(ChartDataModeService $chartMode): void
    {
        $assets = Asset::where('is_active', true)->get();
        $useRealCharts = $chartMode->isReal();

        foreach ($assets as $asset) {
            if (! $useRealCharts && $asset->admin_override_active && $asset->admin_price !== null) {
                continue;
            }

            $newPrice = $this->fetchPrice($asset);

            if ($newPrice === null) {
                continue;
            }

            $oldPrice = (string) $asset->live_price;
            $change = bccomp($oldPrice, '0', 8) > 0
                ? bcmul(bcdiv(bcsub($newPrice, $oldPrice, 8), $oldPrice, 8), '100', 4)
                : '0';

            $asset->update([
                'live_price' => $newPrice,
                'price_change_24h' => $change,
                'price_updated_at' => now(),
            ]);

            PriceHistory::create([
                'asset_id' => $asset->id,
                'price' => $newPrice,
                'open' => $oldPrice,
                'high' => bccomp($newPrice, $oldPrice, 8) > 0 ? $newPrice : $oldPrice,
                'low' => bccomp($newPrice, $oldPrice, 8) < 0 ? $newPrice : $oldPrice,
                'close' => $newPrice,
                'source' => 'live_api',
                'interval' => '1m',
                'recorded_at' => now(),
            ]);

            event(new PriceUpdated($asset->fresh(), $newPrice, 'live_api'));
        }
    }

    protected function fetchPrice(Asset $asset): ?string
    {
        $config = $asset->api_config ?? [];
        $apiKey = config('services.metals_api.key') ?? env('METALS_API_KEY');

        if ($apiKey && ($config['provider'] ?? null) === 'metals_api') {
            try {
                $response = Http::timeout(10)->get('https://metals-api.com/api/latest', [
                    'access_key' => $apiKey,
                    'base' => $config['base'] ?? 'USD',
                    'symbols' => $config['symbol'] ?? $asset->symbol,
                ]);

                if ($response->successful()) {
                    $rates = $response->json('rates', []);
                    $symbol = $config['symbol'] ?? $asset->symbol;

                    if (isset($rates[$symbol])) {
                        $rate = (string) $rates[$symbol];

                        return bccomp($rate, '0', 8) > 0 ? bcdiv('1', $rate, 8) : $rate;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Metals API fetch failed', ['asset' => $asset->symbol, 'error' => $e->getMessage()]);
            }
        }

        if (($config['provider'] ?? null) === 'yahoo' || ! empty($config['yahoo_symbol'])) {
            $yahooPrice = $this->fetchYahooPrice($asset);
            if ($yahooPrice !== null) {
                return $yahooPrice;
            }
        }

        if (($config['provider'] ?? null) === 'binance' || $asset->symbol === 'USDT') {
            try {
                $pair = $config['pair'] ?? 'USDTUSD';
                $response = Http::timeout(10)->get('https://api.binance.com/api/v3/ticker/price', [
                    'symbol' => str_replace('/', '', $pair),
                ]);

                if ($response->successful() && $response->json('price')) {
                    return (string) $response->json('price');
                }
            } catch (\Throwable $e) {
                Log::warning('Binance fetch failed', ['asset' => $asset->symbol, 'error' => $e->getMessage()]);
            }
        }

        return $this->mockPrice($asset);
    }

    protected function fetchYahooPrice(Asset $asset): ?string
    {
        $yahooSymbol = app(\App\Services\RealMarketChartService::class)->resolveYahooSymbol($asset);
        if (! $yahooSymbol) {
            return null;
        }

        try {
            $encoded = rawurlencode($yahooSymbol);
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MBXZone/1.0)'])
                ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$encoded}", [
                    'interval' => '1d',
                    'range' => '1d',
                ]);

            if ($response->successful()) {
                $price = $response->json('chart.result.0.meta.regularMarketPrice');
                if ($price !== null) {
                    return number_format((float) $price, 8, '.', '');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Yahoo price fetch failed', ['asset' => $asset->symbol, 'error' => $e->getMessage()]);
        }

        return null;
    }

    protected function mockPrice(Asset $asset): string
    {
        $base = match ($asset->symbol) {
            'XAU' => '2650',
            'XAG' => '31',
            'USDT' => '1',
            'BTC' => '97500',
            'ETH' => '3650',
            'EURUSD' => '1.085',
            'GBPUSD' => '1.265',
            'USDJPY' => '156.2',
            'SPX' => '5900',
            'NAS100' => '21400',
            'US30' => '42500',
            'WTI' => '78.5',
            default => bccomp((string) $asset->live_price, '0', 8) > 0 ? (string) $asset->live_price : '100',
        };

        $jitter = bcmul($base, bcdiv((string) random_int(-50, 50), '10000', 8), 8);

        return bcadd($base, $jitter, 8);
    }
}

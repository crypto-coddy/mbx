<?php

namespace Tests\Feature;

use App\Models\TradeSetting;
use App\Services\ChartDataModeService;
use App\Services\MarketChartService;
use Tests\TestCase;

class MarketChartServiceTest extends TestCase
{
    public function test_generates_upward_series(): void
    {
        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 1000,
            'chart_trend' => 'up',
        ]);

        $service = app(MarketChartService::class);
        $series = $service->generateSeries($asset, 20);

        $this->assertCount(20, $series);
        $first = (float) $series[0]['price'];
        $last = (float) $series[19]['price'];
        $this->assertLessThan($last, $first);
        $this->assertEquals('up', $series[0]['trend']);
    }

    public function test_set_trend_down_produces_hold_signal(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_CUSTOM],
        );

        $asset = $this->testAsset([
            'name' => 'Silver',
            'symbol' => 'XAG',
            'display_name' => 'Silver',
            'live_price' => 30,
            'chart_trend' => 'up',
        ]);

        $service = app(MarketChartService::class);
        $service->setTrend($asset, 'up');
        $asset = $service->setTrend($asset, 'down');
        $payload = $service->formatAssetForApi($asset);

        $this->assertEquals('down', $payload['chart_trend']);
        $this->assertEquals('hold', $payload['market_signal']);
        $this->assertNotEmpty($payload['chart']);
        $this->assertNotEmpty($payload['chart_candles']);
        $this->assertNotNull($payload['chart_summary']['transition_index']);
        $this->assertEquals('up', $payload['chart_summary']['previous_trend']);

        $trends = array_column($payload['chart'], 'trend');
        $this->assertContains('up', $trends);
        $this->assertContains('down', $trends);
    }

    public function test_series_to_candles_adds_realistic_wicks(): void
    {
        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
            'chart_trend' => 'down',
        ]);

        $service = app(MarketChartService::class);
        $series = $service->generateSeries($asset, 40, 'down');
        $candles = $service->seriesToCandles($series, 24);

        $this->assertGreaterThanOrEqual(12, count($candles));

        $withWicks = 0;
        foreach ($candles as $candle) {
            $open = (float) $candle['open'];
            $high = (float) $candle['high'];
            $low = (float) $candle['low'];
            $close = (float) $candle['close'];

            $this->assertGreaterThanOrEqual(max($open, $close), $high);
            $this->assertLessThanOrEqual(min($open, $close), $low);

            if ($high > max($open, $close) || $low < min($open, $close)) {
                $withWicks++;
            }
        }

        $this->assertGreaterThan(0, $withWicks);
    }

    public function test_append_trend_keeps_up_segment_before_down(): void
    {
        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
            'chart_trend' => 'up',
        ]);

        $service = app(MarketChartService::class);
        $upSeries = $service->generateSeries($asset, 24, 'up');
        $merged = $service->appendTrendSegment($upSeries, $asset, 'up', 'down');

        $firstDownIndex = null;
        foreach ($merged as $i => $point) {
            if ($point['trend'] === 'down' && ($merged[$i - 1]['trend'] ?? null) === 'up') {
                $firstDownIndex = $i;
                break;
            }
        }

        $this->assertNotNull($firstDownIndex);
        $this->assertGreaterThan(5, $firstDownIndex);
    }
}

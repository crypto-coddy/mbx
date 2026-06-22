@php
    $preview = $this->previewPayload;
    $candles = $preview['chart_candles'] ?? [];

    $chartWidth = 960;
    $chartHeight = 320;
    $padTop = 16;
    $padBottom = 28;
    $padLeft = 12;
    $padRight = 72;
    $plotW = $chartWidth - $padLeft - $padRight;
    $plotH = $chartHeight - $padTop - $padBottom;

    $lows = array_map(fn ($c) => (float) $c['low'], $candles);
    $highs = array_map(fn ($c) => (float) $c['high'], $candles);
    $min = min($lows ?: [0]);
    $max = max($highs ?: [1]);
    $range = max($max - $min, $max * 0.0005, 0.01);
    $min -= $range * 0.08;
    $max += $range * 0.08;
    $range = $max - $min;

    $priceToY = function (float $price) use ($min, $range, $padTop, $plotH): float {
        return $padTop + (1 - (($price - $min) / $range)) * $plotH;
    };

    $count = count($candles);
    $slotW = $count > 0 ? $plotW / $count : $plotW;
    $bodyW = max(4, min(12, $slotW * 0.72));
    $wickW = 1.5;

    $gridLines = 4;
    $shapes = '';

    for ($g = 0; $g <= $gridLines; $g++) {
        $y = $padTop + ($plotH / $gridLines) * $g;
        $shapes .= '<line x1="'.($padLeft).'" y1="'.$y.'" x2="'.($padLeft + $plotW).'" y2="'.$y.'" stroke="rgba(148,163,184,0.18)" stroke-width="1"/>';
        $price = $max - (($max - $min) / $gridLines) * $g;
        $shapes .= '<text x="'.($chartWidth - $padRight + 8).'" y="'.($y + 4).'" fill="#64748b" font-size="11">$'.number_format($price, 2).'</text>';
    }

    foreach ($candles as $index => $candle) {
        $open = (float) $candle['open'];
        $high = (float) $candle['high'];
        $low = (float) $candle['low'];
        $close = (float) $candle['close'];
        $bull = $close >= $open;
        $color = $bull ? '#16a34a' : '#dc2626';

        $centerX = $padLeft + ($slotW * $index) + ($slotW / 2);
        $yHigh = $priceToY($high);
        $yLow = $priceToY($low);
        $yOpen = $priceToY($open);
        $yClose = $priceToY($close);
        $bodyTop = min($yOpen, $yClose);
        $bodyHeight = max(abs($yClose - $yOpen), 2);

        $shapes .= '<line x1="'.$centerX.'" y1="'.$yHigh.'" x2="'.$centerX.'" y2="'.$yLow.'" stroke="'.$color.'" stroke-width="'.$wickW.'"/>';
        $shapes .= '<rect x="'.($centerX - ($bodyW / 2)).'" y="'.$bodyTop.'" width="'.$bodyW.'" height="'.$bodyHeight.'" fill="'.$color.'" rx="1"/>';
    }
@endphp

<div
    id="assets-live-chart-preview"
    class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
>
    <div class="assets-live-chart-header">
        <div class="assets-live-header-copy">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-600">Mobile trade chart preview</p>
            <h3 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                {{ $preview['symbol'] }} — {{ $preview['name'] }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Twelve Data: {{ $preview['td_symbol'] ?? 'n/a' }} · 1min OHLC candles
                @if ($preview['source'] === 'quote_fallback')
                    · using quote fallback (time_series unavailable)
                @endif
            </p>
        </div>

        <div class="assets-live-header-controls">
            <label class="flex flex-col gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                Preview asset
                <select
                    wire:model.live="previewSymbol"
                    class="min-w-[220px] rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                >
                    @foreach ($this->assetOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            @if ($preview['live_price'] !== null)
                <div class="rounded-lg bg-gray-50 px-4 py-2 text-right dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Live</p>
                    <p class="text-lg font-bold text-gray-950 dark:text-white">
                        ${{ number_format((float) $preview['live_price'], 2) }}
                    </p>
                    @if ($preview['percent_change'] !== null)
                        <p @class([
                            'text-xs font-semibold',
                            'text-success-600' => (float) $preview['percent_change'] >= 0,
                            'text-danger-600' => (float) $preview['percent_change'] < 0,
                        ])>
                            {{ ((float) $preview['percent_change'] >= 0 ? '+' : '').number_format((float) $preview['percent_change'], 2) }}%
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if (! empty($candles))
        @php
            $sessionOpen = (float) ($preview['session_open'] ?? 0);
            $sessionClose = (float) ($preview['session_close'] ?? 0);
            $closeBullish = $sessionClose >= $sessionOpen;
        @endphp
        <div class="border-b border-gray-100 assets-live-ohlc-row dark:border-gray-800">
            <div class="assets-live-ohlc-pills">
                <span class="assets-live-ohlc-pill assets-live-ohlc-pill--open">
                    <span class="assets-live-ohlc-label">O</span>
                    <span class="assets-live-ohlc-value">${{ number_format($sessionOpen, 2) }}</span>
                </span>
                <span class="assets-live-ohlc-pill assets-live-ohlc-pill--high">
                    <span class="assets-live-ohlc-label">H</span>
                    <span class="assets-live-ohlc-value">${{ number_format((float) ($preview['session_high'] ?? 0), 2) }}</span>
                </span>
                <span class="assets-live-ohlc-pill assets-live-ohlc-pill--low">
                    <span class="assets-live-ohlc-label">L</span>
                    <span class="assets-live-ohlc-value">${{ number_format((float) ($preview['session_low'] ?? 0), 2) }}</span>
                </span>
                <span @class([
                    'assets-live-ohlc-pill',
                    'assets-live-ohlc-pill--close-up' => $closeBullish,
                    'assets-live-ohlc-pill--close-down' => ! $closeBullish,
                ])>
                    <span class="assets-live-ohlc-label">C</span>
                    <span class="assets-live-ohlc-value">${{ number_format($sessionClose, 2) }}</span>
                </span>
            </div>
        </div>
    @endif

    <div wire:key="assets-live-chart-{{ $previewSymbol }}" class="relative px-2 pb-2 pt-1">
        @if (empty($candles))
            <div class="flex h-[320px] items-center justify-center rounded-lg bg-gray-50 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                {{ $preview['error'] ?? 'No candle data available for this symbol yet. Click Refresh live data or try another asset.' }}
            </div>
        @else
            <div class="w-full overflow-x-auto rounded-lg bg-white dark:bg-gray-950">
                <svg
                    viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
                    preserveAspectRatio="none"
                    class="block h-[320px] w-full"
                    role="img"
                    aria-label="{{ $preview['symbol'] }} candlestick preview"
                >
                    <rect x="0" y="0" width="{{ $chartWidth }}" height="{{ $chartHeight }}" fill="transparent" />
                    {!! $shapes !!}
                </svg>
            </div>
        @endif
    </div>

    <div class="assets-live-chart-footer">
        Same OHLC feed as the mobile trade screen can use later. {{ $preview['candles_count'] }} candles shown.
    </div>
</div>

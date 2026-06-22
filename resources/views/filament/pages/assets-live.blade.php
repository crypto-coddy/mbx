<x-filament-panels::page>
    @include('filament.pages.partials.assets-live-styles')

    @if ($tradeChartLive)
        <div wire:poll.60s="pollLiveData"></div>
    @endif

    <div class="assets-live-trade-chart-panel">
        <div class="assets-live-trade-chart-panel-inner">
            <div class="assets-live-trade-chart-copy">
                <p class="assets-live-trade-chart-title">Trade chart (admin web only)</p>
                <p class="assets-live-trade-chart-desc">
                    Start or stop live OHLC updates on this page. Does not change mobile app charts.
                </p>
            </div>

            <div class="assets-live-trade-chart-actions">
                <span @class([
                    'assets-live-status-badge',
                    'assets-live-status-badge--live' => $tradeChartLive,
                    'assets-live-status-badge--paused' => ! $tradeChartLive,
                ])>
                    {{ $tradeChartLive ? 'Live' : 'Paused' }}
                </span>

                <button
                    type="button"
                    wire:click="toggleTradeChartLive"
                    wire:loading.attr="disabled"
                    @class([
                        'assets-live-toggle-btn',
                        'assets-live-toggle-btn--stop' => $tradeChartLive,
                        'assets-live-toggle-btn--start' => ! $tradeChartLive,
                    ])
                >
                    @if ($tradeChartLive)
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5 0V9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Stop trade chart
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z"/></svg>
                        Start trade chart
                    @endif
                </button>
            </div>
        </div>
    </div>

    <div class="assets-live-info-panel">
        <div class="assets-live-info-panel-inner">
            <div>
                <p class="assets-live-info-title">Live OHLC from Twelve Data</p>
                <p class="assets-live-info-desc">
                    Basic plan = <strong>8 API calls/minute</strong>.
                    @if ($tradeChartLive)
                        Auto-refreshes every {{ \App\Services\TwelveDataService::CACHE_TTL_SECONDS }} seconds while trade chart is live.
                    @else
                        Auto-refresh is <strong>paused</strong> — use Refresh live data for a one-off update.
                    @endif
                </p>
                @if ($rateLimitMessage)
                    <p class="assets-live-rate-limit">{{ $rateLimitMessage }}</p>
                @endif
            </div>
            @if ($fetchedAt)
                <span class="assets-live-updated-badge">
                    Updated {{ \Illuminate\Support\Carbon::parse($fetchedAt)->diffForHumans() }}
                </span>
            @endif
        </div>
    </div>

    @include('filament.pages.partials.assets-live-candle-chart')

    {{ $this->table }}

    @script
    <script>
        $wire.on('scroll-to-chart-preview', () => {
            document.getElementById('assets-live-chart-preview')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    </script>
    @endscript
</x-filament-panels::page>

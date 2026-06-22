<x-filament-panels::page>
    @if ($tradeChartLive)
        <div wire:poll.60s="pollLiveData"></div>
    @endif

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 dark:text-white">Trade chart (admin web only)</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Start or stop live OHLC updates on this page. Does not change mobile app charts.
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
                    'bg-success-50 text-success-700 dark:bg-success-950 dark:text-success-300' => $tradeChartLive,
                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ! $tradeChartLive,
                ])>
                    {{ $tradeChartLive ? 'Live' : 'Paused' }}
                </span>

                <button
                    type="button"
                    wire:click="toggleTradeChartLive"
                    wire:loading.attr="disabled"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition',
                        'bg-warning-600 text-white hover:bg-warning-500' => $tradeChartLive,
                        'bg-success-600 text-white hover:bg-success-500' => ! $tradeChartLive,
                    ])
                >
                    @if ($tradeChartLive)
                        <x-filament::icon icon="heroicon-o-pause-circle" class="h-5 w-5" />
                        Stop trade chart
                    @else
                        <x-filament::icon icon="heroicon-o-play-circle" class="h-5 w-5" />
                        Start trade chart
                    @endif
                </button>
            </div>
        </div>
    </div>

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium text-gray-900 dark:text-white">Live OHLC from Twelve Data</p>
                <p class="mt-1">
                    Basic plan = <strong>8 API calls/minute</strong>.
                    @if ($tradeChartLive)
                        Auto-refreshes every {{ \App\Services\TwelveDataService::CACHE_TTL_SECONDS }} seconds while trade chart is live.
                    @else
                        Auto-refresh is <strong>paused</strong> — use Refresh live data for a one-off update.
                    @endif
                </p>
                @if ($rateLimitMessage)
                    <p class="mt-2 rounded-lg bg-warning-50 px-3 py-2 text-warning-700 dark:bg-warning-950 dark:text-warning-300">
                        {{ $rateLimitMessage }}
                    </p>
                @endif
            </div>
            @if ($fetchedAt)
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
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

<x-filament-panels::page wire:poll.60s="pollLiveData">
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium text-gray-900 dark:text-white">Live OHLC from Twelve Data</p>
                <p class="mt-1">
                    Basic plan = <strong>8 API calls/minute</strong>. This page uses ~3 calls/min (quotes + chart candles).
                    Auto-refreshes every {{ \App\Services\TwelveDataService::CACHE_TTL_SECONDS }} seconds.
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

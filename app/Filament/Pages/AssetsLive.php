<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Services\MarketChartService;
use App\Services\TwelveDataService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AssetsLive extends Page implements HasTable
{
    use AuthorizesAdminPermission;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Markets Live (v2)';

    protected static ?string $title = 'Assets Live';

    protected static ?string $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'assets-live';

    protected static string $view = 'filament.pages.assets-live';

    /** @var array<string, array<string, mixed>> */
    public array $liveData = [];

    public ?string $fetchedAt = null;

    public ?string $rateLimitMessage = null;

    public string $previewSymbol = 'XAU';

    /** Auto-refresh quotes + preview chart on this admin page only (not mobile). */
    public bool $tradeChartLive = false;

    public static function canAccess(): bool
    {
        return static::canAdmin('view_markets');
    }

    public function mount(): void
    {
        // Off by default — no Twelve Data calls until admin clicks Start or Refresh live data.
    }

    public function updatedPreviewSymbol(TwelveDataService $twelveData): void
    {
        $this->refreshPreviewCandles($this->previewSymbol, $twelveData, force: true);
    }

    public function previewChart(string $symbol, TwelveDataService $twelveData): void
    {
        $this->previewSymbol = $symbol;
        $this->refreshPreviewCandles($symbol, $twelveData, force: true);
        $this->dispatch('scroll-to-chart-preview');
    }

    public function pollLiveData(TwelveDataService $twelveData): void
    {
        if (! $this->tradeChartLive) {
            return;
        }

        $this->refreshLiveData($twelveData);
        $this->refreshPreviewCandles($this->previewSymbol, $twelveData);
    }

    public function toggleTradeChartLive(): void
    {
        $this->tradeChartLive = ! $this->tradeChartLive;

        if ($this->tradeChartLive) {
            $this->pollLiveData(app(TwelveDataService::class));

            Notification::make()
                ->title('Trade chart live updates started')
                ->body('Quotes and preview candles will refresh every '.TwelveDataService::CACHE_TTL_SECONDS.' seconds.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Trade chart live updates stopped')
            ->body('Auto-refresh paused on this page. Use Refresh live data for a manual update.')
            ->info()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreviewPayloadProperty(): array
    {
        $asset = Asset::query()->where('symbol', $this->previewSymbol)->first();
        $payload = $this->liveData[$this->previewSymbol] ?? [];
        $rawCandles = $payload['candles'] ?? [];
        $chartCandles = app(TwelveDataService::class)->normalizeCandlesForChart($rawCandles);
        $source = 'twelve_data_time_series';

        if (count($chartCandles) < 2) {
            $chartCandles = app(TwelveDataService::class)->syntheticCandlesFromQuote([
                'open' => $payload['open'] ?? null,
                'high' => $payload['high'] ?? null,
                'low' => $payload['low'] ?? null,
                'close' => $payload['close'] ?? $payload['live_price'] ?? null,
            ]);
            $source = count($chartCandles) >= 2 ? 'quote_fallback' : 'unavailable';
        } elseif (($payload['source'] ?? '') === 'twelve_data_time_series') {
            $source = 'twelve_data_time_series';
        }

        $first = $chartCandles[0] ?? null;
        $last = $chartCandles[array_key_last($chartCandles)] ?? null;

        return [
            'symbol' => $this->previewSymbol,
            'name' => $asset?->name ?? $this->previewSymbol,
            'td_symbol' => TwelveDataService::SYMBOL_MAP[$this->previewSymbol] ?? null,
            'live_price' => $payload['live_price'] ?? null,
            'percent_change' => $payload['percent_change'] ?? null,
            'chart_candles' => $chartCandles,
            'candles_count' => count($chartCandles),
            'source' => $source,
            'error' => $payload['error'] ?? null,
            'session_open' => $first['open'] ?? null,
            'session_high' => collect($chartCandles)->max('high'),
            'session_low' => collect($chartCandles)->min('low'),
            'session_close' => $last['close'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getAssetOptionsProperty(): array
    {
        return Asset::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Asset $asset) => [$asset->symbol => $asset->symbol.' — '.$asset->name])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Asset::query()->orderBy('sort_order'))
            ->columns([
                Tables\Columns\TextColumn::make('symbol')->weight('bold'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('td_symbol')
                    ->label('Twelve Data')
                    ->state(fn (Asset $record) => TwelveDataService::SYMBOL_MAP[$record->symbol] ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('category')
                    ->colors([
                        'warning' => 'commodities',
                        'info' => 'crypto',
                        'success' => 'forex',
                        'gray' => 'indices',
                    ]),
                Tables\Columns\TextColumn::make('live_price')
                    ->label('Live price')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'live_price'))
                    ->formatStateUsing(fn ($state) => $state !== null ? '$'.number_format((float) $state, 2) : '—')
                    ->description(fn (Asset $record) => $this->liveField($record->symbol, 'candle_time') ?: null),
                Tables\Columns\TextColumn::make('open')
                    ->label('Open')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'open'))
                    ->formatStateUsing(fn ($state) => $this->formatPrice($state)),
                Tables\Columns\TextColumn::make('high')
                    ->label('High')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'high'))
                    ->formatStateUsing(fn ($state) => $this->formatPrice($state))
                    ->color('success'),
                Tables\Columns\TextColumn::make('low')
                    ->label('Low')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'low'))
                    ->formatStateUsing(fn ($state) => $this->formatPrice($state))
                    ->color('danger'),
                Tables\Columns\TextColumn::make('close')
                    ->label('Close')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'close'))
                    ->formatStateUsing(fn ($state) => $this->formatPrice($state)),
                Tables\Columns\BadgeColumn::make('chart_trend')
                    ->label('Chart')
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->colors(['success' => 'up', 'danger' => 'down']),
                Tables\Columns\TextColumn::make('percent_change')
                    ->label('24h %')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'percent_change'))
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '—'
                        : (($state >= 0 ? '+' : '').number_format((float) $state, 2).'%'))
                    ->color(fn ($state) => $state === null ? 'gray' : ((float) $state >= 0 ? 'success' : 'danger')),
                Tables\Columns\TextColumn::make('candles_count')
                    ->label('Candles')
                    ->state(fn (Asset $record) => $this->liveField($record->symbol, 'candles_count') ?? 0)
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('trading_enabled')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('previewChart')
                    ->label('Chart')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->color('primary')
                    ->action(fn (Asset $record, TwelveDataService $twelveData) => $this->previewChart($record->symbol, $twelveData)),
                Tables\Actions\Action::make('viewCandles')
                    ->label('Candles')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('info')
                    ->modalHeading(fn (Asset $record) => $record->symbol.' — 1min OHLC candles')
                    ->modalDescription(fn (Asset $record) => 'Twelve Data symbol: '.(TwelveDataService::SYMBOL_MAP[$record->symbol] ?? 'n/a'))
                    ->modalContent(fn (Asset $record) => new HtmlString($this->renderCandlesTable($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('chartUp')
                    ->label('Chart UP')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => static::canAdmin('manage_markets'))
                    ->action(function (Asset $record) {
                        app(MarketChartService::class)->setTrend($record, 'up', auth()->id());
                        Notification::make()->title('Chart set to UP')->success()->send();
                    }),
                Tables\Actions\Action::make('chartDown')
                    ->label('Chart DOWN')
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => static::canAdmin('manage_markets'))
                    ->action(function (Asset $record) {
                        app(MarketChartService::class)->setTrend($record, 'down', auth()->id());
                        Notification::make()->title('Chart set to DOWN')->success()->send();
                    }),
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn () => static::canAdmin('manage_markets'))
                    ->url(fn (Asset $record) => AssetResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('sort_order')
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshLive')
                ->label('Refresh live data')
                ->icon('heroicon-o-arrow-path')
                ->action(function (TwelveDataService $twelveData) {
                    $twelveData->clearCaches();
                    $this->refreshLiveData($twelveData, force: true);
                    $this->refreshPreviewCandles($this->previewSymbol, $twelveData, force: true);

                    $notification = Notification::make()
                        ->title($this->rateLimitMessage ? 'Rate limit — try again in 1 minute' : 'Live market data refreshed');

                    if ($this->rateLimitMessage) {
                        $notification->warning()->body($this->rateLimitMessage);
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }

    protected function refreshLiveData(TwelveDataService $twelveData, bool $force = false): void
    {
        $this->liveData = $twelveData->getLiveDataForAssets(force: $force);
        $this->fetchedAt = collect($this->liveData)
            ->pluck('fetched_at')
            ->filter()
            ->first();
        $this->rateLimitMessage = collect($this->liveData)
            ->pluck('error')
            ->filter(fn (?string $msg) => $msg && str_contains(strtolower($msg), 'rate limit'))
            ->first();
    }

    protected function refreshPreviewCandles(string $symbol, TwelveDataService $twelveData, bool $force = false): void
    {
        $quoteRow = $this->liveData[$symbol] ?? [];
        $series = $twelveData->refreshPreviewCandles($symbol, $force);
        $this->liveData[$symbol] = $twelveData->mergeSymbolPayload($symbol, $quoteRow, $series);

        if (($series['rate_limited'] ?? false) === true) {
            $this->rateLimitMessage = $series['message'] ?? 'Twelve Data rate limit reached.';
        }

        $this->fetchedAt = now()->toIso8601String();
    }

    protected function ensurePreviewCandles(string $symbol, TwelveDataService $twelveData): void
    {
        $this->refreshPreviewCandles($symbol, $twelveData);
    }

    protected function liveField(string $symbol, string $field): mixed
    {
        return $this->liveData[$symbol][$field] ?? null;
    }

    protected function formatPrice(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return '$'.number_format((float) $state, 2);
    }

    protected function renderCandlesTable(Asset $record): string
    {
        $payload = $this->liveData[$record->symbol] ?? [];
        $candles = $payload['candles'] ?? [];
        $error = $payload['error'] ?? null;

        if ($error) {
            return '<div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700">'.e($error).'</div>';
        }

        if ($candles === []) {
            return '<div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600">No candle data returned for this symbol.</div>';
        }

        $rows = '';

        foreach ($candles as $candle) {
            $rows .= '<tr class="border-b border-gray-100">'
                .'<td class="px-3 py-2 text-sm">'.e($candle['datetime'] ?? '—').'</td>'
                .'<td class="px-3 py-2 text-sm">$'.e(number_format((float) ($candle['open'] ?? 0), 2)).'</td>'
                .'<td class="px-3 py-2 text-sm text-success-600">$'.e(number_format((float) ($candle['high'] ?? 0), 2)).'</td>'
                .'<td class="px-3 py-2 text-sm text-danger-600">$'.e(number_format((float) ($candle['low'] ?? 0), 2)).'</td>'
                .'<td class="px-3 py-2 text-sm font-medium">$'.e(number_format((float) ($candle['close'] ?? 0), 2)).'</td>'
                .'<td class="px-3 py-2 text-sm">'.e($candle['volume'] ?? '—').'</td>'
                .'</tr>';
        }

        return '<div class="overflow-x-auto rounded-xl border border-gray-200">'
            .'<table class="min-w-full divide-y divide-gray-200">'
            .'<thead class="bg-gray-50"><tr>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Datetime</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Open</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">High</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Low</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Close</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Volume</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>';
    }
}

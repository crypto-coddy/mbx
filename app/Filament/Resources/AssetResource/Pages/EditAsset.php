<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Services\MarketChartService;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function afterSave(): void
    {
        $asset = $this->record->fresh();
        app(MarketChartService::class)->setTrend(
            $asset,
            $asset->chart_trend ?? 'up',
            auth()->id(),
        );
    }
}

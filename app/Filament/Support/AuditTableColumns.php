<?php

namespace App\Filament\Support;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class AuditTableColumns
{
    /**
     * @return array<int, TextColumn>
     */
    public static function make(bool $hiddenByDefault = true): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('d M Y, H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: $hiddenByDefault),
            Tables\Columns\TextColumn::make('creator.name')
                ->label('Created by')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: $hiddenByDefault),
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Updated')
                ->dateTime('d M Y, H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updater.name')
                ->label('Updated by')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}

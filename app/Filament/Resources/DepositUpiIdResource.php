<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\DepositUpiIdResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\DepositUpiId;
use App\Services\UpiQrService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DepositUpiIdResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = DepositUpiId::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Mobile Configuration';

    protected static ?string $navigationLabel = 'Deposit UPI IDs';

    protected static ?string $modelLabel = 'deposit UPI ID';

    protected static ?string $pluralModelLabel = 'Deposit UPI IDs';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_mobile_config');
    }

    public static function canCreate(): bool
    {
        return static::canAdmin('manage_mobile_config');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_mobile_config');
    }

    public static function canDelete($record): bool
    {
        return static::canAdmin('manage_mobile_config');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('UPI details')
                ->description('Only active UPI IDs are shown to users in the mobile deposit screen.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Display label')
                        ->maxLength(120)
                        ->placeholder('Primary UPI')
                        ->helperText('Optional name shown above the UPI ID on mobile'),
                    Forms\Components\TextInput::make('upi_id')
                        ->label('UPI ID')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('quantx@upi')
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->helperText('Example: merchant@paytm, quantx@upi'),
                    Forms\Components\TextInput::make('payee_name')
                        ->label('Payee name (QR)')
                        ->maxLength(120)
                        ->placeholder('QuantX Payments')
                        ->live(onBlur: true)
                        ->helperText('Optional name embedded in the UPI QR code'),
                    Forms\Components\Toggle::make('show_qr_code')
                        ->label('Show QR code on mobile')
                        ->default(true)
                        ->live()
                        ->helperText('Generates a scannable UPI payment QR from the UPI ID above'),
                    Forms\Components\Placeholder::make('qr_preview')
                        ->label('QR code preview')
                        ->content(function (Forms\Get $get, ?DepositUpiId $record): HtmlString {
                            if (! $get('show_qr_code')) {
                                return new HtmlString('<span class="text-sm text-gray-500">QR code hidden on mobile for this UPI ID.</span>');
                            }

                            $upiId = trim((string) ($get('upi_id') ?? $record?->upi_id ?? ''));

                            if ($upiId === '') {
                                return new HtmlString('<span class="text-sm text-gray-500">Enter a UPI ID to preview the QR code.</span>');
                            }

                            $payeeName = $get('payee_name') ?? $record?->payee_name;

                            return new HtmlString(app(UpiQrService::class)->previewHtml($upiId, $payeeName));
                        })
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Inactive UPI IDs are hidden from users')
                        ->default(true)
                        ->live(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Higher numbers appear first when multiple UPI IDs are active'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('upi_id')
                    ->label('UPI ID')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('show_qr_code')
                    ->label('QR')
                    ->boolean()
                    ->tooltip('Show QR code on mobile'),
                Tables\Columns\TextColumn::make('payee_name')
                    ->label('Payee')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                ...AuditTableColumns::make(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepositUpiIds::route('/'),
            'create' => Pages\CreateDepositUpiId::route('/create'),
            'edit' => Pages\EditDepositUpiId::route('/{record}/edit'),
        ];
    }
}

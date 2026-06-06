<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\KycDocumentResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\KycDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KycDocumentResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = KycDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'KYC Documents';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_kyc');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_kyc');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->required(),
            Forms\Components\Select::make('document_type')
                ->options([
                    'aadhaar_front' => 'Aadhaar front',
                    'aadhaar_back' => 'Aadhaar back',
                    'pan_card' => 'PAN card',
                    'bank_passbook' => 'Bank passbook',
                    'bank_statement' => 'Bank statement',
                    'selfie' => 'Selfie',
                    'other' => 'Other',
                ]),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            Forms\Components\Textarea::make('rejection_reason'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('User'),
            Tables\Columns\TextColumn::make('document_type'),
            Tables\Columns\BadgeColumn::make('status'),
            ...AuditTableColumns::make(false),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKycDocuments::route('/'),
            'edit' => Pages\EditKycDocument::route('/{record}/edit'),
        ];
    }
}

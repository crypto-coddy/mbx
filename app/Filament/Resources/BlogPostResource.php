<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\BlogPost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Space Blog';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_blog');
    }

    public static function canCreate(): bool
    {
        return static::canAdmin('manage_blog');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_blog');
    }

    public static function canDelete($record): bool
    {
        return static::canAdmin('manage_blog');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Post')
                ->description('Published posts appear in the mobile app Space tab.')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('URL-safe id, e.g. market-update-june'),
                    Forms\Components\Textarea::make('excerpt')
                        ->rows(2)
                        ->columnSpanFull()
                        ->helperText('Short summary shown in the Space list'),
                    Forms\Components\RichEditor::make('body')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('cover_image_url')
                        ->label('Cover image URL')
                        ->url()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Publish')
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Published')
                        ->helperText('Must be ON for the post to show on mobile')
                        ->default(false)
                        ->live(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish date')
                        ->helperText('Leave empty to publish immediately when Published is ON')
                        ->default(now()),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Higher numbers appear first'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->copyable(),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Live'),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                ...AuditTableColumns::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}

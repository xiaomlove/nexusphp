<?php

namespace App\Filament\Resources\News;

use App\Filament\Resources\News\NewsResource\Pages\CreateNews;
use App\Filament\Resources\News\NewsResource\Pages\EditNews;
use App\Filament\Resources\News\NewsResource\Pages\ListNews;
use App\Models\News;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Filament 5 admin resource for the `news` table — replaces the
 * legacy `public/news.php` script (deleted in the same PR). The
 * legacy URL `/news.php` 302-redirects to `/nexusphp/news` so admin
 * bookmarks and the inline `[news page]` link rendered by
 * `public/index.php` keep working — see `routes/web.php`.
 *
 * The legacy script dispatched on `?action=` with three branches:
 *
 *   - `?action=add` (POST)            → INSERT into `news`, fire
 *                                       `news_created` event, redirect.
 *   - `?action=edit&newsid=N` (GET)   → render compose form prefilled
 *                                       with the row.
 *   - `?action=edit&newsid=N` (POST)  → UPDATE row, drop the public
 *                                       `recent_news` cache.
 *   - `?action=delete&newsid=N&sure=1`→ DELETE row, drop the cache.
 *   - default                         → render compose form for a
 *                                       brand-new entry.
 *
 * All five flows collapse into the standard Filament list / create
 * / edit pages under this resource. The cache invalidation and the
 * `news_created` event firing both move into `App\Models\News`'s
 * boot hooks, so any other writer (including legacy code that still
 * `INSERT`s through `NexusDB`) gets the same observable side
 * effects.
 *
 * Permission gating uses {@see user_can()} so the configurable
 * `$AUTHORITY['newsmanage']` threshold is honored — Power Users on
 * a tracker that has lowered the threshold still get access. This
 * mirrors the legacy `user_can('newsmanage', true)` gate at the top
 * of `public/news.php`.
 */
class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static ?string $slug = 'news';

    protected static ?string $pluralModelLabel = 'Site News';

    protected static ?string $label = 'News item';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 65;

    public static function getNavigationLabel(): string
    {
        return 'News Management';
    }

    /**
     * Honor the configurable `$AUTHORITY['newsmanage']` threshold.
     * Defaults to class 14 = Administrator, but a tracker that has
     * lowered the bar via `settings.php` still grants access. Mirrors
     * the legacy `user_can('newsmanage', true)` gate.
     */
    public static function canAccess(): bool
    {
        return user_can('newsmanage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            // The legacy compose form fed `body` straight into the
            // public renderer (which runs `format_comment()` over it),
            // so HTML / BBCode is the expected input format. We
            // intentionally do not escape on save — the public side
            // handles output escaping.
            Textarea::make('body')
                ->label('Body (HTML / BBCode allowed)')
                ->required()
                ->rows(15)
                ->columnSpanFull(),

            DateTimePicker::make('added')
                ->label('Posted at')
                ->seconds(false)
                ->helperText('Leave blank to use the current time when the row is created.')
                ->columnSpanFull(),

            // The `notify` enum drives the "send a notification PM
            // to every user" behaviour the legacy `news_created`
            // event listener implements. Stored as the literal
            // strings 'yes' / 'no' to match the existing column.
            Toggle::make('notify')
                ->label('Notify users of this news item')
                ->dehydrateStateUsing(fn ($state): string => $state ? 'yes' : 'no')
                ->formatStateUsing(fn ($state): bool => $state === 'yes')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('added')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('user.username')
                    ->label('Author')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),

                IconColumn::make('notify')
                    ->label('Notified')
                    ->boolean()
                    ->getStateUsing(fn (News $record): bool => $record->notify === 'yes'),
            ])
            ->defaultSort('added', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNews::route('/'),
            'create' => CreateNews::route('/create'),
            'edit' => EditNews::route('/{record}/edit'),
        ];
    }
}

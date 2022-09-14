<?php

namespace App\Filament\Resources\System\SectionResource\RelationManagers;

use App\Filament\Resources\System\SectionResource\TaxonomyTrait;
use App\Models\SearchBox;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxonomySourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'taxonomy_source';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $torrentField = 'source';

    protected static function getModelLabel(): string
    {
        static $taxonomies;
        if (!$taxonomies) {
            $params = request()->all();
            $taxonomies = $params['serverMemo']['data']['data']['extra'][SearchBox::EXTRA_TAXONOMY_LABELS] ?? [];
            if (empty($taxonomies)) {
                $id = request()->route()->parameter('record');
                if (!$id) {
                    $id = $params['serverMemo']['dataMeta']['models']['ownerRecord']['id'] ?? null;
                }
                do_log("searchBox ID: $id");
                $searchBox = SearchBox::query()->find($id);
                if ($searchBox) {
                    $taxonomies = $searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] ?? [];
                } else {
                    $taxonomies = [];
                }
            }
        }
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy['torrent_field'] == static::$torrentField) {
                return $taxonomy['display_text'];
            }
        }
        $field = static::$torrentField;
        return __("label.search_box.$field") ?? $field;
    }

    public static function shouldShowTaxonomy(SearchBox $searchBox): bool
    {
        $taxonomies = $searchBox->extra[SearchBox::EXTRA_TAXONOMY_LABELS] ?? [];
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy['torrent_field'] == static::$torrentField) {
                do_log("torrent_field: " . static::$torrentField . " should show");
                return true;
            }
        }
        do_log("torrent_field: " . static::$torrentField . " don't show");
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord): bool
    {
        return self::shouldShowTaxonomy($ownerRecord);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('sort_index')
                    ->default(0)
                    ->label(__('label.search_box.taxonomy.sort_index'))
                    ->helperText(__('label.search_box.taxonomy.sort_index_help'))
                ,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.search_box.taxonomy.name')),
                Tables\Columns\TextColumn::make('sort_index')->label(__('label.search_box.taxonomy.sort_index'))->sortable(),
            ])
            ->defaultSort('sort_index')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->after(function ($record) {
                    clear_search_box_cache($record->mode);
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(function ($record) {
                    clear_search_box_cache($record->mode);
                }),
                Tables\Actions\DeleteAction::make()->after(function ($record) {
                    clear_search_box_cache($record->mode);
                }),
            ])
            ->bulkActions([

            ]);
    }

}

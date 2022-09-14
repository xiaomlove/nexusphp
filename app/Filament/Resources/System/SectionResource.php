<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\SectionResource\Pages;
use App\Filament\Resources\System\SectionResource\RelationManagers;
use App\Models\Forum;
use App\Models\SearchBox;
use App\Models\TorrentCustomField;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SectionResource extends Resource
{
    protected static ?string $model = SearchBox::class;

    protected static ?string $slug = 'sections';

    protected static ?string $pluralModelLabel = 'Section';

    protected static ?string $label = 'Section';

    protected static ?string $navigationIcon = 'heroicon-o-view-boards';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.section');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label(__('label.search_box.name'))->rules('alpha_dash')->required(),
                Forms\Components\TextInput::make('catsperrow')
                    ->label(__('label.search_box.catsperrow'))
                    ->helperText(__('label.search_box.catsperrow_help'))
                    ->integer()
                    ->required()
                    ->default(8)
                ,
                Forms\Components\TextInput::make('catpadding')
                    ->label(__('label.search_box.catpadding'))
                    ->helperText(__('label.search_box.catpadding_help'))
                    ->integer()
                    ->required()
                    ->default(3)
                ,
                Forms\Components\TextInput::make('section_name')
                    ->label(__('label.search_box.section_name'))
                    ->helperText(__('label.search_box.section_name_help'))
                ,
                Forms\Components\CheckboxList::make('custom_fields')
                    ->options(TorrentCustomField::getCheckboxOptions())
                    ->label(__('label.search_box.custom_fields'))
                ,
                Forms\Components\TextInput::make('custom_fields_display_name')
                    ->label(__('label.search_box.custom_fields_display_name'))
                ,
                Forms\Components\Textarea::make('custom_fields_display')
                    ->label(__('label.search_box.custom_fields_display'))
                    ->helperText(__('label.search_box.custom_fields_display_help'))
                    ->columnSpan(['sm' => 'full'])
                ,
                Forms\Components\Toggle::make('is_default')
                    ->label(__('label.search_box.is_default'))
                    ->columnSpan(['sm' => 'full'])
                ,
                Forms\Components\Toggle::make('showsubcat')->label(__('label.search_box.showsubcat')),
                Forms\Components\Section::make(__('label.search_box.showsubcat'))->schema([
                    Forms\Components\Repeater::make('extra.' . SearchBox::EXTRA_TAXONOMY_LABELS)
                        ->schema([
                            Forms\Components\Select::make('torrent_field')->options(SearchBox::getSubCatOptions())->label(__('label.search_box.torrent_field')),
                            Forms\Components\TextInput::make('display_text')->label(__('label.search_box.taxonomy_display_text')),
                        ])
                        ->label(__('label.search_box.taxonomies'))->columns(2)
                        ->rules([
                            function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $fields = [];
                                    foreach ($value as $item) {
                                        if (!in_array($item['torrent_field'], $fields)) {
                                            $fields[] = $item['torrent_field'];
                                        } else {
                                            $fail(__('label.search_box.torrent_field_duplicate', ['field' => $item['torrent_field']]));
                                        }
                                    }
                                };
                            }
                        ])
                    ,
                ]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.search_box.name')),
                Tables\Columns\TextColumn::make('section_name')->label(__('label.search_box.section_name')),
                Tables\Columns\BooleanColumn::make('is_default')->label(__('label.search_box.is_default')),
                Tables\Columns\BooleanColumn::make('showsubcat')->label(__('label.search_box.showsubcat')),
                Tables\Columns\BooleanColumn::make('showsource'),
                Tables\Columns\BooleanColumn::make('showmedium'),
                Tables\Columns\BooleanColumn::make('showcodec'),
                Tables\Columns\BooleanColumn::make('showstandard'),
                Tables\Columns\BooleanColumn::make('showprocessing'),
                Tables\Columns\BooleanColumn::make('showteam'),
                Tables\Columns\BooleanColumn::make('showaudiocodec'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoriesRelationManager::class,
            RelationManagers\TaxonomySourcesRelationManager::class,
            RelationManagers\TaxonomyMediumRelationManager::class,
            RelationManagers\TaxonomyCodecsRelationManager::class,
            RelationManagers\TaxonomyAudioCodecsRelationManager::class,
            RelationManagers\TaxonomyTeamsRelationManager::class,
            RelationManagers\TaxonomyStandardsRelationManager::class,
            RelationManagers\TaxonomyProcessingRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSections::route('/'),
            'create' => Pages\CreateSection::route('/create'),
            'edit' => Pages\EditSection::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\SecondIconResource\Pages;
use App\Filament\Resources\Section\SecondIconResource\RelationManagers;
use App\Models\SearchBox;
use App\Models\SecondIcon;
use App\Models\Setting;
use App\Repositories\SearchBoxRepository;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Nexus\Database\NexusDB;

class SecondIconResource extends Resource
{
    protected static ?string $model = SecondIcon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Section';

    protected static ?int $navigationSort = 11;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.second_icon');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        $searchBoxRep = new SearchBoxRepository();
        $torrentMode = Setting::get('main.browsecat');
        $specialMode = Setting::get('main.specialcat');
        $torrentTaxonomySchema = $searchBoxRep->listTaxonomyFormSchema($torrentMode);
        $specialTaxonomySchema = $searchBoxRep->listTaxonomyFormSchema($specialMode);
        $modeOptions = SearchBox::listModeOptions();
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('label.name'))
                    ->required()
                    ->helperText(__('label.second_icon.name_help'))
                ,
                Forms\Components\TextInput::make('image')
                    ->label(__('label.second_icon.image'))
                    ->required()
                    ->helperText(__('label.second_icon.image_help'))
                ,
                Forms\Components\TextInput::make('class_name')
                    ->label(__('label.second_icon.class_name'))
                    ->helperText(__('label.second_icon.class_name_help'))
                ,
                Forms\Components\Select::make('mode')
                    ->options($modeOptions)
                    ->label(__('label.search_box.taxonomy.mode'))
                    ->helperText(__('label.search_box.taxonomy.mode_help'))
                    ->reactive()
                ,
                Forms\Components\Section::make(__('label.second_icon.select_section'))
                    ->id("taxonomy_$torrentMode")
                    ->schema($torrentTaxonomySchema)
                    ->columns(4)
                    ->hidden(fn (\Closure $get) => $get('mode') != $torrentMode)
                ,
                Forms\Components\Section::make(__('label.second_icon.select_section'))
                    ->id("taxonomy_$specialMode")
                    ->schema($specialTaxonomySchema)
                    ->columns(4)
                    ->hidden(fn (\Closure $get) => $get('mode') != $specialMode)
                ,

            ]);
    }



    public static function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('id'),
            Tables\Columns\TextColumn::make('search_box.name')
                ->label(__('label.search_box.label'))
                ->formatStateUsing(fn ($record) => $record->search_box->name ?? 'All')
            ,
            Tables\Columns\TextColumn::make('name')->label(__('label.name')),
            Tables\Columns\TextColumn::make('image')->label(__('label.second_icon.image')),
            Tables\Columns\TextColumn::make('class_name')->label(__('label.second_icon.class_name')),
        ];
        $taxonomyList = self::listTaxonomy();
        foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
            $columns[] = Tables\Columns\TextColumn::make($torrentField)->formatStateUsing(function ($state) use ($taxonomyList, $torrentField) {
                 return $taxonomyList[$torrentField]->get($state);
            });
        }
        return $table
            ->columns($columns)
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

    private static function listTaxonomy()
    {
        static $taxonomyList = [];
        if (empty($taxonomyList)) {
            foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
                $taxonomyList[$torrentField] = NexusDB::table($taxonomyTableModel['table'])->pluck('name', 'id');
            }
        }
        return $taxonomyList;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecondIcons::route('/'),
            'create' => Pages\CreateSecondIcon::route('/create'),
            'edit' => Pages\EditSecondIcon::route('/{record}/edit'),
        ];
    }
}

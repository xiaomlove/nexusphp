<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\CategoryResource\Pages;
use App\Filament\Resources\Section\CategoryResource\RelationManagers;
use App\Models\Category;
use App\Models\Icon;
use App\Models\NexusModel;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\SearchBoxRepository;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Section';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.category');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('mode')
                    ->options(SearchBox::listModeOptions())
                    ->label(__('label.search_box.label'))
                    ->rules([
                        'required',
                        function () {
                            return function ($attribute, $value, $fail) {
                                //@todo how to get the editing record ?
                                $exists = Torrent::query()->where('category', $value)->exists();
                                do_log("check $attribute: $value torrent if exists: $exists");
//                                if ($exists) {
//                                    $fail("There are torrents belonging to this category that cannot be changed!");
//                                }
                            };
                        }
                    ])
                ,
                Forms\Components\TextInput::make('name')->required()->label(__('label.search_box.taxonomy.name'))->required(),
                Forms\Components\TextInput::make('image')
                    ->label(__('label.search_box.taxonomy.image'))
                    ->helperText(__('label.search_box.taxonomy.image_help'))
                    ->required()
                ,
                Forms\Components\Select::make('icon_id')
                    ->options(Icon::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.taxonomy.icon_id'))
                    ->required()
                ,
                Forms\Components\TextInput::make('class_name')
                    ->label(__('label.search_box.taxonomy.class_name'))
                    ->helperText(__('label.search_box.taxonomy.class_name_help'))
                ,
                Forms\Components\TextInput::make('sort_index')
                    ->default(0)
                    ->label(__('label.priority'))
                    ->helperText(__('label.priority_help'))
                ,

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('search_box.name')->label(__('label.search_box.label')),
                Tables\Columns\TextColumn::make('name')->label(__('label.search_box.taxonomy.name'))->searchable(),
                Tables\Columns\TextColumn::make('icon.name')->label(__('label.search_box.taxonomy.icon_id')),
                Tables\Columns\TextColumn::make('image')->label(__('label.search_box.taxonomy.image')),
                Tables\Columns\TextColumn::make('class_name')->label(__('label.search_box.taxonomy.class_name')),
                Tables\Columns\TextColumn::make('sort_index')->label(__('label.search_box.taxonomy.sort_index'))->sortable(),
            ])
            ->defaultSort('sort_index', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('mode')
                    ->options(SearchBox::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.label'))
                ,
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->using(function (NexusModel $record) {
                    try {
                        $rep = new SearchBoxRepository();
                        $rep->deleteCategory($record->id);
                    } catch (\Exception $exception) {
                        Filament::notify('danger', $exception->getMessage() ?: class_basename($exception));
                    }
                }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->using(function (Collection $records) {
                    try {
                        $rep = new SearchBoxRepository();
                        $rep->deleteCategory($records->pluck('id')->toArray());
                    } catch (\Exception $exception) {
                        Filament::notify('danger', $exception->getMessage() ?: class_basename($exception));
                    }
                }),
            ]);
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}

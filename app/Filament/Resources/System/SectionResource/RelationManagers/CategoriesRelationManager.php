<?php

namespace App\Filament\Resources\System\SectionResource\RelationManagers;

use App\Models\Icon;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function getModelLabel(): string
    {
        return __('label.search_box.category');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Tables\Columns\TextColumn::make('icon.name')->label(__('label.search_box.taxonomy.icon_id')),
                Tables\Columns\TextColumn::make('image')->label(__('label.search_box.taxonomy.image')),
                Tables\Columns\TextColumn::make('class_name')->label(__('label.search_box.taxonomy.class_name')),
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

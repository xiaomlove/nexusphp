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
                Forms\Components\TextInput::make('name')->required()->label(__('label.search_box.taxonomy.name')),
                Forms\Components\TextInput::make('image')
                    ->rule('alpha_dash')
                    ->label(__('label.search_box.taxonomy.image'))
                    ->helperText(__('label.search_box.taxonomy.image_help'))
                ,
                Forms\Components\Select::make('icon_id')
                    ->options(Icon::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.taxonomy.icon_id'))
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
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}

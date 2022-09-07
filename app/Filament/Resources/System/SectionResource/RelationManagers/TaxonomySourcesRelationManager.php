<?php

namespace App\Filament\Resources\System\SectionResource\RelationManagers;

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
    protected static string $relationship = 'taxonomy_sources';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function getModelLabel(): string
    {

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

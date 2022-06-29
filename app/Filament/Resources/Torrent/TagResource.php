<?php

namespace App\Filament\Resources\Torrent;

use App\Filament\Resources\Torrent\TagResource\Pages;
use App\Filament\Resources\Torrent\TagResource\RelationManagers;
use App\Models\Tag;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Torrent';

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.tags_list');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('color')->required()->label('Background color'),
                Forms\Components\TextInput::make('font_color')->required(),
                Forms\Components\TextInput::make('font_size')->required(),
                Forms\Components\TextInput::make('margin')->required(),
                Forms\Components\TextInput::make('padding')->required(),
                Forms\Components\TextInput::make('border_radius')->required(),
                Forms\Components\TextInput::make('priority')->integer(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('color')->label('Background color'),
                Tables\Columns\TextColumn::make('font_color'),
                Tables\Columns\TextColumn::make('font_size'),
                Tables\Columns\TextColumn::make('margin'),
                Tables\Columns\TextColumn::make('padding'),
                Tables\Columns\TextColumn::make('border_radius'),
                Tables\Columns\TextColumn::make('priority'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}

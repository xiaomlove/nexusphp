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

    protected static ?int $navigationSort = 2;

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
                Forms\Components\TextInput::make('name')->required()->label(__('label.name')),
                Forms\Components\TextInput::make('color')->required()->label(__('label.tag.color')),
                Forms\Components\TextInput::make('font_color')->required()->label(__('label.tag.font_color')),
                Forms\Components\TextInput::make('font_size')->required()->label(__('label.tag.font_size')),
                Forms\Components\TextInput::make('margin')->required()->label(__('label.tag.margin')),
                Forms\Components\TextInput::make('padding')->required()->label(__('label.tag.padding')),
                Forms\Components\TextInput::make('border_radius')->required()->label(__('label.tag.border_radius')),
                Forms\Components\TextInput::make('priority')->integer()->label(__('label.priority')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.name'))->searchable(),
                Tables\Columns\TextColumn::make('color')->label(__('label.tag.color')),
                Tables\Columns\TextColumn::make('font_color')->label(__('label.tag.font_color')),
                Tables\Columns\TextColumn::make('font_size')->label(__('label.tag.font_size')),
                Tables\Columns\TextColumn::make('margin')->label(__('label.tag.margin')),
                Tables\Columns\TextColumn::make('padding')->label(__('label.tag.padding')),
                Tables\Columns\TextColumn::make('border_radius')->label(__('label.tag.border_radius')),
                Tables\Columns\TextColumn::make('priority')->label(__('label.priority'))->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->label(__('label.updated_at')),
            ])
            ->defaultSort('priority', 'desc')
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

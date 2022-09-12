<?php

namespace App\Filament\Resources\System;

use App\Filament\OptionsTrait;
use App\Filament\Resources\System\CategoryIconResource\Pages;
use App\Filament\Resources\System\CategoryIconResource\RelationManagers;
use App\Models\Icon;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryIconResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Icon::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.icon');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label(__('label.name'))->required(),
                Forms\Components\TextInput::make('folder')
                    ->label(__('label.icon.folder'))
                    ->helperText(__('label.icon.folder_help'))
                    ->required()
                ,
                Forms\Components\Radio::make('multilang')
                    ->options(self::$yesOrNo)
                    ->default('no')
                    ->label(__('label.icon.multilang'))
                    ->helperText(__('label.icon.multilang_help'))
                ,
                Forms\Components\Radio::make('secondicon')
                    ->options(self::$yesOrNo)
                    ->default('no')
                    ->label(__('label.icon.secondicon'))
                    ->helperText(__('label.icon.secondicon_help'))
                ,
                Forms\Components\TextInput::make('cssfile')
                    ->label(__('label.icon.cssfile'))
                    ->helperText(__('label.icon.cssfile_help'))
                ,
                Forms\Components\TextInput::make('designer')
                    ->label(__('label.icon.designer'))
                    ->helperText(__('label.icon.designer_help'))
                ,
                Forms\Components\TextInput::make('comment')
                    ->label(__('label.icon.comment'))
                    ->helperText(__('label.icon.comment_help'))
                ,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.name')),
                Tables\Columns\TextColumn::make('folder')->label(__('label.icon.folder')),
                Tables\Columns\TextColumn::make('multilang')->label(__('label.icon.multilang')),
                Tables\Columns\TextColumn::make('secondicon')->label(__('label.icon.secondicon')),
                Tables\Columns\TextColumn::make('cssfile')->label(__('label.icon.cssfile')),
                Tables\Columns\TextColumn::make('designer')->label(__('label.icon.designer')),
                Tables\Columns\TextColumn::make('comment')->label(__('label.icon.comment')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([

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
            'index' => Pages\ListCategoryIcons::route('/'),
            'create' => Pages\CreateCategoryIcon::route('/create'),
            'edit' => Pages\EditCategoryIcon::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\MedalResource\Pages;
use App\Filament\Resources\System\MedalResource\RelationManagers;
use App\Models\Medal;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MedalResource extends Resource
{
    protected static ?string $model = Medal::class;

    protected static ?string $navigationIcon = 'heroicon-o-badge-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.medals_list');
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
                Forms\Components\TextInput::make('price')->required()->integer()->label(__('label.price')),
                Forms\Components\TextInput::make('image_large')->required()->label(__('label.medal.image_large')),
                Forms\Components\TextInput::make('image_small')->required()->label(__('label.medal.image_small')),
                Forms\Components\Radio::make('get_type')
                    ->options(Medal::listGetTypes(true))
                    ->inline()
                    ->columnSpan(['sm' => 2])
                    ->label(__('label.medal.get_type'))
                    ->required(),
                Forms\Components\TextInput::make('duration')
                    ->integer()
                    ->columnSpan(['sm' => 2])
                    ->label(__('label.medal.duration'))
                    ->helperText(__('label.medal.duration_help')),
                Forms\Components\Textarea::make('description')->columnSpan(['sm' => 2])->label(__('label.description')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->label(__('label.name'))->searchable(),
                Tables\Columns\ImageColumn::make('image_large')->height(120)->label(__('label.medal.image_large')),
                Tables\Columns\ImageColumn::make('image_small')->height(120)->label(__('label.medal.image_small')),
                Tables\Columns\TextColumn::make('getTypeText')->label('Get type')->label(__('label.medal.get_type')),
                Tables\Columns\TextColumn::make('price')->label(__('label.price')),
                Tables\Columns\TextColumn::make('duration')->label(__('label.medal.duration')),
            ])
            ->defaultSort('id', 'desc')
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
            'index' => Pages\ListMedals::route('/'),
            'create' => Pages\CreateMedal::route('/create'),
            'edit' => Pages\EditMedal::route('/{record}/edit'),
        ];
    }
}

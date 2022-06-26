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

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.medals_list');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('price')->required()->integer(),
                Forms\Components\TextInput::make('image_large')->required(),
                Forms\Components\TextInput::make('image_small')->required(),
                Forms\Components\Radio::make('get_type')->options(Medal::listGetTypes(true))->inline()->columnSpan(['sm' => 2])->required(),
                Forms\Components\TextInput::make('duration')->integer()->columnSpan(['sm' => 2])->helperText('Unit: day, if empty, belongs to user forever.'),
                Forms\Components\Textarea::make('description')->columnSpan(['sm' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\ImageColumn::make('image_large')->height(120),
                Tables\Columns\ImageColumn::make('image_small')->height(120),
                Tables\Columns\TextColumn::make('getTypeText')->label('Get type'),
                Tables\Columns\TextColumn::make('price'),
                Tables\Columns\TextColumn::make('duration'),
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
            'index' => Pages\ListMedals::route('/'),
            'create' => Pages\CreateMedal::route('/create'),
            'edit' => Pages\EditMedal::route('/{record}/edit'),
        ];
    }
}

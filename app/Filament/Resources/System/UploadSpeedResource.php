<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\UploadSpeedResource\Pages;
use App\Filament\Resources\System\UploadSpeedResource\RelationManagers;
use App\Models\UploadSpeed;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UploadSpeedResource extends Resource
{
    protected static ?string $model = UploadSpeed::class;

    protected static ?string $navigationIcon = 'heroicon-o-upload';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.upload_speed');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label(__('label.name'))
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.name')),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUploadSpeeds::route('/'),
        ];
    }
}

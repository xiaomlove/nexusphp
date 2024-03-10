<?php

namespace App\Filament\Resources\Oauth;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\ClientResource\Pages;
use App\Filament\Resources\Oauth\ClientResource\RelationManagers;
use Laravel\Passport\Client;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Oauth';

    protected static ?int $navigationSort = 1;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.oauth_client');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label(__('label.name')),
                Forms\Components\TextInput::make('redirect')->label(__('oauth.redirect')),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.name')),
                Tables\Columns\TextColumn::make('secret')->label(__('oauth.secret')),
                Tables\Columns\TextColumn::make('redirect')->label(__('oauth.redirect')),

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
            'index' => Pages\ManageClients::route('/'),
        ];
    }
}

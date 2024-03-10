<?php

namespace App\Filament\Resources\Oauth;

use App\Filament\Resources\Oauth\RefreshTokenResource\Pages;
use App\Filament\Resources\Oauth\RefreshTokenResource\RelationManagers;
use Laravel\Passport\RefreshToken;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RefreshTokenResource extends Resource
{
    protected static ?string $model = RefreshToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Oauth';

    protected static ?int $navigationSort = 4;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.oauth_refresh_token');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('oauth.refresh_token'))
                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('access_token_id')
                    ->label(__('oauth.access_token'))
                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('label.expire_at'))
            ])
            ->filters([
                //
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRefreshTokens::route('/'),
        ];
    }
}

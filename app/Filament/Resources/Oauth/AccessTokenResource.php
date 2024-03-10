<?php

namespace App\Filament\Resources\Oauth;

use App\Filament\Resources\Oauth\AccessTokenResource\Pages;
use App\Filament\Resources\Oauth\AccessTokenResource\RelationManagers;
use Laravel\Passport\Token;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccessTokenResource extends Resource
{
    protected static ?string $model = Token::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Oauth';

    protected static ?int $navigationSort = 3;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.oauth_access_token');
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
                Tables\Columns\TextColumn::make('id')->searchable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('label.username'))
                    ->formatStateUsing(fn ($record) => username_for_admin($record->user_id)),
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('oauth.client')),
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
            'index' => Pages\ManageAccessTokens::route('/'),
        ];
    }
}

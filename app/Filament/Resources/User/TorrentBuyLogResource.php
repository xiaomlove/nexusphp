<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\TorrentBuyLogResource\Pages;
use App\Filament\Resources\User\TorrentBuyLogResource\RelationManagers;
use App\Models\TorrentBuyLog;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TorrentBuyLogResource extends Resource
{
    protected static ?string $model = TorrentBuyLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 10;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.torrent_buy_log');
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
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uid')
                    ->formatStateUsing(fn ($state) => username_for_admin($state))
                    ->label(__('label.username'))
                ,
                Tables\Columns\TextColumn::make('torrent_id')
                    ->formatStateUsing(fn ($record) => torrent_name_for_admin($record->torrent))
                    ->label(__('label.torrent.label'))
                ,
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->label(__('label.price'))
                ,
                Tables\Columns\TextColumn::make('created_at')
                    ->formatStateUsing(fn ($state) => format_datetime($state))
                    ->label(__('label.created_at'))
                ,
            ])
            ->defaultSort('id','desc')
            ->filters([
                Tables\Filters\Filter::make('uid')
                    ->form([
                        Forms\Components\TextInput::make('uid')
                            ->label(__('label.username'))
                            ->placeholder('UID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['uid'], fn (Builder $query, $value) => $query->where("uid", $value));
                    })
                ,
                Tables\Filters\Filter::make('torrent_id')
                    ->form([
                        Forms\Components\TextInput::make('torrent_id')
                            ->label(__('label.torrent.label'))
                            ->placeholder('Torrent ID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['torrent_id'], fn (Builder $query, $value) => $query->where("torrent_id", $value));
                    })
                ,
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTorrentBuyLogs::route('/'),
            'create' => Pages\CreateTorrentBuyLog::route('/create'),
            'edit' => Pages\EditTorrentBuyLog::route('/{record}/edit'),
        ];
    }
}

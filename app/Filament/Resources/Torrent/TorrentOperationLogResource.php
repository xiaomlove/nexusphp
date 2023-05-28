<?php

namespace App\Filament\Resources\Torrent;

use App\Filament\Resources\Torrent\TorrentOperationLogResource\Pages;
use App\Filament\Resources\Torrent\TorrentOperationLogResource\RelationManagers;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TorrentOperationLogResource extends Resource
{
    protected static ?string $model = TorrentOperationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Torrent';

    protected static ?int $navigationSort = 4;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.torrent_operation_log');
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
                Tables\Columns\TextColumn::make('user.username')
                    ->formatStateUsing(fn ($record) => username_for_admin($record->uid))
                    ->label(__('label.user.label'))
                ,
                Tables\Columns\TextColumn::make('torrent.name')
                    ->formatStateUsing(fn ($record) => torrent_name_for_admin($record->torrent))
                    ->label(__('label.torrent.label'))
                ,
                Tables\Columns\TextColumn::make('action_type_text')
                    ->label(__('torrent-operation-log.fields.action_type'))
                ,
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('label.comment'))
                ,

                Tables\Columns\TextColumn::make('created_at')
                    ->formatStateUsing(fn ($state) => format_datetime($state))
                    ->label(__('label.created_at'))
                ,
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('uid')
                    ->form([
                        Forms\Components\TextInput::make('uid')
                            ->placeholder('UID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['uid'], fn (Builder $query, $value) => $query->where("uid", $value));
                    })
                ,
                Tables\Filters\Filter::make('torrent_id')
                    ->form([
                        Forms\Components\TextInput::make('torrent_id')
                            ->placeholder('Torrent ID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['torrent_id'], fn (Builder $query, $value) => $query->where("torrent_id", $value));
                    })
                ,
                Tables\Filters\SelectFilter::make('action_type')
                    ->options(TorrentOperationLog::listStaticProps(TorrentOperationLog::$actionTypes, 'torrent.operation_log.%s.type_text', true))
                    ->label(__('torrent-operation-log.fields.action_type'))
                    ->multiple()
                ,
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
//                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTorrentOperationLogs::route('/'),
        ];
    }
}

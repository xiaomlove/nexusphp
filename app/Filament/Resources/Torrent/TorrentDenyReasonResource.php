<?php

namespace App\Filament\Resources\Torrent;

use App\Filament\Resources\Torrent\TorrentDenyReasonResource\Pages;
use App\Filament\Resources\Torrent\TorrentDenyReasonResource\RelationManagers;
use App\Models\TorrentDenyReason;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TorrentDenyReasonResource extends Resource
{
    protected static ?string $model = TorrentDenyReason::class;

    protected static ?string $navigationIcon = 'heroicon-o-ban';

    protected static ?string $navigationGroup = 'Torrent';

    protected static ?int $navigationSort = 3;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.torrent_deny_reason');
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
                Forms\Components\TextInput::make('priority')->integer()->label(__('label.priority'))->default(0),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.name')),
                Tables\Columns\TextColumn::make('priority')->label(__('label.priority'))->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('label.created_at')),
            ])
            ->defaultSort('priority', 'desc')
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
            'index' => Pages\ManageTorrentDenyReasons::route('/'),
        ];
    }
}

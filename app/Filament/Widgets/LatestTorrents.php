<?php

namespace App\Filament\Widgets;

use App\Models\Torrent;
use Closure;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestTorrents extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getTableHeading(): string | Closure | null
    {
        return __('dashboard.latest_torrent.page_title');
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    protected function getTableQuery(): Builder
    {
        return Torrent::query()->orderBy('id', 'desc')->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label(__('label.name'))
                ->formatStateUsing(fn ($record) => torrent_name_for_admin($record, false, 30))
            ,
            Tables\Columns\TextColumn::make('owner')
                ->label(__('label.torrent.owner'))
                ->formatStateUsing(fn ($state) => username_for_admin($state))
            ,
            Tables\Columns\TextColumn::make('size')->formatStateUsing(fn ($state) => mksize($state))->label(__('label.torrent.size')),
            Tables\Columns\TextColumn::make('added')->dateTime()->label(__('label.added')),
        ];
    }
}

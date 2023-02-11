<?php

namespace App\Filament\Resources\User\TorrentBuyLogResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\TorrentBuyLogResource;
use App\Models\TorrentBuyLog;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTorrentBuyLogs extends PageList
{
    protected static string $resource = TorrentBuyLogResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return TorrentBuyLog::query()->with(['user', 'torrent']);
    }
}

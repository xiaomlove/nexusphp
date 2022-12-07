<?php

namespace App\Filament\Resources\Torrent\TorrentOperationLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Torrent\TorrentOperationLogResource;
use App\Models\TorrentOperationLog;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageTorrentOperationLogs extends PageListSingle
{
    protected static string $resource = TorrentOperationLogResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return TorrentOperationLog::query()->with(['torrent', 'user']);
    }
}

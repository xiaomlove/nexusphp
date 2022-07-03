<?php

namespace App\Filament\Resources\Torrent\TorrentResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Torrent\TorrentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTorrents extends PageList
{
    protected static string $resource = TorrentResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

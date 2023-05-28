<?php

namespace App\Filament\Resources\Torrent\TorrentResource\Pages;

use App\Filament\Resources\Torrent\TorrentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTorrent extends CreateRecord
{
    protected static string $resource = TorrentResource::class;
}

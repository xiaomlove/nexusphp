<?php

namespace App\Filament\Resources\Torrent\TorrentResource\Pages;

use App\Filament\Resources\Torrent\TorrentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTorrent extends EditRecord
{
    protected static string $resource = TorrentResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

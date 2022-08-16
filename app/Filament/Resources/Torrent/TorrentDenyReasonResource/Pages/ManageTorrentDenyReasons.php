<?php

namespace App\Filament\Resources\Torrent\TorrentDenyReasonResource\Pages;

use App\Filament\Resources\Torrent\TorrentDenyReasonResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTorrentDenyReasons extends ManageRecords
{
    protected ?string $maxContentWidth = 'full';

    protected static string $resource = TorrentDenyReasonResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

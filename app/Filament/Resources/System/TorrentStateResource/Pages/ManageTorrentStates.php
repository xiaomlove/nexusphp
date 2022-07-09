<?php

namespace App\Filament\Resources\System\TorrentStateResource\Pages;

use App\Filament\Resources\System\TorrentStateResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;
use Nexus\Database\NexusDB;

class ManageTorrentStates extends ManageRecords
{
    protected static string $resource = TorrentStateResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

}

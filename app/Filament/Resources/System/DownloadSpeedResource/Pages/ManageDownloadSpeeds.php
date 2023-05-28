<?php

namespace App\Filament\Resources\System\DownloadSpeedResource\Pages;

use App\Filament\Resources\System\DownloadSpeedResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDownloadSpeeds extends ManageRecords
{
    protected static string $resource = DownloadSpeedResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

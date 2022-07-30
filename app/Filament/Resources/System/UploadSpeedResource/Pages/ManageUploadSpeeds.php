<?php

namespace App\Filament\Resources\System\UploadSpeedResource\Pages;

use App\Filament\Resources\System\UploadSpeedResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUploadSpeeds extends ManageRecords
{
    protected static string $resource = UploadSpeedResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

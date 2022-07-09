<?php

namespace App\Filament\Resources\System\MedalResource\Pages;

use App\Filament\Resources\System\MedalResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMedal extends CreateRecord
{
    protected static string $resource = MedalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

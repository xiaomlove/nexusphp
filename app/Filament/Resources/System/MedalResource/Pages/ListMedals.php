<?php

namespace App\Filament\Resources\System\MedalResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\MedalResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedals extends PageList
{
    protected static string $resource = MedalResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

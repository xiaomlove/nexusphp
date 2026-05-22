<?php

namespace App\Filament\Resources\System\LocationResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\LocationResource;
use Filament\Actions\CreateAction;

class ListLocations extends PageList
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

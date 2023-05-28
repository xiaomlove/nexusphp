<?php

namespace App\Filament\Resources\Section\IconResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\IconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIcons extends PageList
{
    protected static string $resource = IconResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

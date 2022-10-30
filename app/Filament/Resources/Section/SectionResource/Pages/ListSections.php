<?php

namespace App\Filament\Resources\Section\SectionResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\SectionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSections extends PageList
{
    protected static string $resource = SectionResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }


}

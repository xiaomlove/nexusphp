<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\SeedBoxRecordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSeedBoxRecords extends PageList
{
    protected static string $resource = SeedBoxRecordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

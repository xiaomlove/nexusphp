<?php

namespace App\Filament\Resources\Torrent\TagResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Torrent\TagResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTags extends PageList
{
    protected static string $resource = TagResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

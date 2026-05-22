<?php

namespace App\Filament\Resources\News\NewsResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\News\NewsResource;
use Filament\Actions\CreateAction;

class ListNews extends PageList
{
    protected static string $resource = NewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

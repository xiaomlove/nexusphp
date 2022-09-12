<?php

namespace App\Filament\Resources\System\CategoryIconResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\CategoryIconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoryIcons extends PageList
{
    protected static string $resource = CategoryIconResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

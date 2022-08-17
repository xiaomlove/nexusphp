<?php

namespace App\Filament\Resources\Permission\RoleResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Permission\RoleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends PageList
{
    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

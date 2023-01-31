<?php

namespace App\Filament\Resources\User\LoginLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\User\LoginLogResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLoginLogs extends PageListSingle
{
    protected static string $resource = LoginLogResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

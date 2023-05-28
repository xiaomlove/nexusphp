<?php

namespace App\Filament\Resources\System\SettingResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\SettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSettings extends PageList
{
    protected static string $resource = SettingResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\User\UserMedalResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\UserMedalResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserMedals extends PageList
{
    protected static string $resource = UserMedalResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

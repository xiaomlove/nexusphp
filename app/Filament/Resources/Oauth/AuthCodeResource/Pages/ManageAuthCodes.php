<?php

namespace App\Filament\Resources\Oauth\AuthCodeResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\AuthCodeResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAuthCodes extends PageListSingle
{
    protected static string $resource = AuthCodeResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

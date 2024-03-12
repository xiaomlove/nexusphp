<?php

namespace App\Filament\Resources\Oauth\ClientResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\ClientResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageClients extends PageListSingle
{
    protected static string $resource = ClientResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Oauth\AccessTokenResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\AccessTokenResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAccessTokens extends PageListSingle
{
    protected static string $resource = AccessTokenResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

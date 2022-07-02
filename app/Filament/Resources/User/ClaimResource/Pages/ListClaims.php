<?php

namespace App\Filament\Resources\User\ClaimResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\ClaimResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClaims extends PageList
{
    protected static string $resource = ClaimResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\User\BonusLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\User\BonusLogResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBonusLogs extends PageListSingle
{
    protected static string $resource = BonusLogResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

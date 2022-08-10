<?php

namespace App\Filament\Resources\System\UsernameChangeLogResource\Pages;

use App\Filament\Resources\System\UsernameChangeLogResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUsernameChangeLogs extends ManageRecords
{
    protected ?string $maxContentWidth = 'full';

    protected static string $resource = UsernameChangeLogResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

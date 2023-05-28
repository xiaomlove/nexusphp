<?php

namespace App\Filament\Resources\System\IspResource\Pages;

use App\Filament\Resources\System\IspResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageIsps extends ManageRecords
{
    protected static string $resource = IspResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Permission\PermissionResource\Pages;

use App\Filament\Resources\Permission\PermissionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

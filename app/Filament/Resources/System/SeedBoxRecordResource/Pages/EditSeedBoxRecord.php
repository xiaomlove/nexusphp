<?php

namespace App\Filament\Resources\System\SeedBoxRecordResource\Pages;

use App\Filament\Resources\System\SeedBoxRecordResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeedBoxRecord extends EditRecord
{
    protected static string $resource = SeedBoxRecordResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\CategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Nexus\Database\NexusDB;

class EditCategory extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = CategoryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @see functions.php::get_category_row()
     */
    protected function afterSave()
    {
        clear_category_cache();
    }
}

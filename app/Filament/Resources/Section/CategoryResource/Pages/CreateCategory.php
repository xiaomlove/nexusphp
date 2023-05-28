<?php

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\CreateRedirectIndexTrait;
use App\Filament\Resources\Section\CategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use CreateRedirectIndexTrait;

    protected static string $resource = CategoryResource::class;

    protected function afterCreate()
    {
        clear_category_cache();
    }
}

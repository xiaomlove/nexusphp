<?php

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\Resources\Section\CategoryResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}

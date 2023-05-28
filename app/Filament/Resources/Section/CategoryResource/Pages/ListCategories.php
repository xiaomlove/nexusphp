<?php

namespace App\Filament\Resources\Section\CategoryResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\CategoryResource;
use App\Models\Category;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCategories extends PageList
{
    protected static string $resource = CategoryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Category::query()->with('search_box')->orderBy('mode', 'asc');
    }


}

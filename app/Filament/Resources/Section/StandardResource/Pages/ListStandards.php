<?php

namespace App\Filament\Resources\Section\StandardResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\StandardResource;
use App\Models\Codec;
use App\Models\Standard;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStandards extends PageList
{
    protected static string $resource = StandardResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Standard::query()->with('search_box')->orderBy('mode', 'asc');
    }
}

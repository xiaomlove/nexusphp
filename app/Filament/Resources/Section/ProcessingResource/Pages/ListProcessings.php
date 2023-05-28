<?php

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\ProcessingResource;
use App\Models\Processing;
use App\Models\Source;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProcessings extends PageList
{
    protected static string $resource = ProcessingResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Processing::query()->with('search_box')->orderBy('mode', 'asc');
    }
}

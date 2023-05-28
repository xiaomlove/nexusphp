<?php

namespace App\Filament\Resources\Section\SourceResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\SourceResource;
use App\Models\Source;
use App\Models\Team;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSources extends PageList
{
    protected static string $resource = SourceResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Source::query()->with('search_box')->orderBy('mode', 'asc');
    }
}

<?php

namespace App\Filament\Resources\Section\MediaResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\MediaResource;
use App\Models\Media;
use App\Models\Source;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMedia extends PageList
{
    protected static string $resource = MediaResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Media::query()->with('search_box')->orderBy('mode', 'asc');
    }
}

<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\CodecResource;
use App\Models\Codec;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCodecs extends PageList
{
    protected static string $resource = CodecResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Codec::query()->with('search_box')->orderBy('mode', 'asc');
    }
}

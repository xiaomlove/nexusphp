<?php

namespace App\Filament\Resources\Torrent\TagResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Torrent\TagResource;
use App\Models\Tag;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTags extends PageList
{
    protected static string $resource = TagResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }


    protected function getTableQuery(): Builder
    {
        return Tag::query()->withCount('torrents')->withSum('torrents', 'size');
    }

}

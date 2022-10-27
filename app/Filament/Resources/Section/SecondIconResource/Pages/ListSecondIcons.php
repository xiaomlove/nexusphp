<?php

namespace App\Filament\Resources\Section\SecondIconResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Section\SecondIconResource;
use App\Models\SecondIcon;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSecondIcons extends PageList
{
    protected static string $resource = SecondIconResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return SecondIcon::query()->with('search_box');
    }
}

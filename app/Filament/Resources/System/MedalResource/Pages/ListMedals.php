<?php

namespace App\Filament\Resources\System\MedalResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\MedalResource;
use App\Models\Medal;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMedals extends PageList
{
    protected static string $resource = MedalResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Medal::query()->withCount('users');
    }
}

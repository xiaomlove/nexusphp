<?php

namespace App\Filament\Resources\User\HitAndRunResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\HitAndRunResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHitAndRuns extends PageList
{
    protected static string $resource = HitAndRunResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

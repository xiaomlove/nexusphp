<?php

namespace NexusPlugin\Tracker\Filament\TrackerResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Oauth\ClientResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;
use NexusPlugin\Tracker\Filament\TrackerResource;

class ManageTracker extends PageListSingle
{
    protected static string $resource = TrackerResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

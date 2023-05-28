<?php

namespace App\Filament\Resources\Section\TeamResource\Pages;

use App\Filament\Resources\Section\TeamResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;

class CreateTeam extends CreateCodec
{
    protected static string $resource = TeamResource::class;
}

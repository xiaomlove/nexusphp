<?php

namespace App\Filament\Resources\Section\StandardResource\Pages;

use App\Filament\Resources\Section\StandardResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;

class CreateStandard extends CreateCodec
{
    protected static string $resource = StandardResource::class;
}

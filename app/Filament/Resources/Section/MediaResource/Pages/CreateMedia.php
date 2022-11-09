<?php

namespace App\Filament\Resources\Section\MediaResource\Pages;

use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;
use App\Filament\Resources\Section\MediaResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMedia extends CreateCodec
{
    protected static string $resource = MediaResource::class;
}

<?php

namespace App\Filament\Resources\Section\SourceResource\Pages;

use App\Filament\Resources\Section\SourceResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;

class CreateSource extends CreateCodec
{
    protected static string $resource = SourceResource::class;
}

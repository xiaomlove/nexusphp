<?php

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\Resources\Section\ProcessingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;

class CreateProcessing extends CreateCodec
{
    protected static string $resource = ProcessingResource::class;
}

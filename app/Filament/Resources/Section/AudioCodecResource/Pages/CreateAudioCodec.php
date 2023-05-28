<?php

namespace App\Filament\Resources\Section\AudioCodecResource\Pages;

use App\Filament\Resources\Section\AudioCodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Section\CodecResource\Pages\CreateCodec;

class CreateAudioCodec extends CreateCodec
{
    protected static string $resource = AudioCodecResource::class;
}

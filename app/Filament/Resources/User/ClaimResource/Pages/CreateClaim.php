<?php

namespace App\Filament\Resources\User\ClaimResource\Pages;

use App\Filament\Resources\User\ClaimResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateClaim extends CreateRecord
{
    protected static string $resource = ClaimResource::class;
}

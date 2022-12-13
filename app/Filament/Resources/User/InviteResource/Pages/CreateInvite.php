<?php

namespace App\Filament\Resources\User\InviteResource\Pages;

use App\Filament\Resources\User\InviteResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvite extends CreateRecord
{
    protected static string $resource = InviteResource::class;
}

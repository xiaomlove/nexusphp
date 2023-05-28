<?php

namespace App\Filament\Resources\User\UserMetaResource\Pages;

use App\Filament\Resources\User\UserMetaResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserMeta extends CreateRecord
{
    protected static string $resource = UserMetaResource::class;
}

<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\Resources\User\UserResource;
use Filament\Resources\Pages\Page;

class UserProfile extends Page
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user.user-resource.pages.user-profile';
}

<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\Resources\User\UserResource;
use App\Models\User;
use Filament\Resources\Pages\Page;

class UserProfile extends Page
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user.user-resource.pages.user-profile';

    protected ?User $user;


    public function mount($record)
    {
        $this->user = User::query()->with(['inviter'])->findOrFail($record);
    }

    protected function getViewData(): array
    {
        return [
            'user' => $this->user,
        ];
    }
}

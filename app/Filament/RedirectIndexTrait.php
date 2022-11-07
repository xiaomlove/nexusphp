<?php

namespace App\Filament;

trait RedirectIndexTrait
{
    protected function getRedirectUrl(): ?string
    {
        return static::$resource::getUrl('index');
    }
}

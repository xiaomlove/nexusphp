<?php

namespace App\Filament\Resources\Faq\FaqResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\Faq\FaqResource;
use Filament\Actions\CreateAction;

class ListFaqs extends PageList
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

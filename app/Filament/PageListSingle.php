<?php

namespace App\Filament;

use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Filters\Layout;

class PageListSingle extends ManageRecords
{
    protected ?string $maxContentWidth = 'full';

    protected function getTableFiltersLayout(): ?string
    {
        return Layout::AboveContent;
    }
}

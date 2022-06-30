<?php

namespace App\Filament\Custom\Widgets;

use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;

class StatTable extends Widget
{
    protected static string $view = 'filament.widgets.stat-table';

    protected function getHeader(): string
    {

    }

    protected function getTableRows(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        return [
            'header' => $this->getHeader(),
            'data' => $this->getTableRows(),
        ];
    }
}

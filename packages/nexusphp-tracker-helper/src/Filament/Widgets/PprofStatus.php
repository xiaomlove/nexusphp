<?php

namespace NexusPlugin\Tracker\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use NexusPlugin\Tracker\TrackerRepository;

class PprofStatus extends StatTable
{
    protected static ?int $sort = 100;

    protected array $data = [];

    protected function getHeader(): string
    {
        return 'Pprof';
    }

    protected function getTableRows(): array
    {
        return TrackerRepository::getInstance()->getWidgetTableRows('Pprof');
    }
}

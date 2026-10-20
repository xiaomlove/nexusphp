<?php

namespace NexusPlugin\Tracker\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use NexusPlugin\Tracker\TrackerRepository;

class RequestStatus extends StatTable
{
    protected static ?int $sort = 1;

    protected array $data = [];

    protected function getHeader(): string
    {
        return 'Request';
    }

    protected function getTableRows(): array
    {
        return TrackerRepository::getInstance()->getWidgetTableRows('RequestStat');
    }
}

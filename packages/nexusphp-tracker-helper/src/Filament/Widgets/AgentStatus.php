<?php

namespace NexusPlugin\Tracker\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use NexusPlugin\Tracker\TrackerRepository;

class AgentStatus extends StatTable
{
    protected static ?int $sort = 1;

    protected function getHeader(): string
    {
        return 'Agent';
    }

    protected function getTableRows(): array
    {
        return TrackerRepository::getInstance()->getWidgetTableRows('Agent');
    }
}

<?php

namespace NexusPlugin\Tracker\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use NexusPlugin\Tracker\TrackerRepository;

class PeerStatus extends StatTable
{
    protected static ?int $sort = 1;

    protected function getHeader(): string
    {
        return 'Peer';
    }

    protected function getTableRows(): array
    {
        return TrackerRepository::getInstance()->getWidgetTableRows('Peer');
    }
}

<?php

namespace NexusPlugin\Tracker\Filament\TrackerResource\Pages;

use Filament\Resources\Pages\Page;
use NexusPlugin\Tracker\Filament\TrackerResource;
use NexusPlugin\Tracker\Filament\Widgets\AgentStatus;
use NexusPlugin\Tracker\Filament\Widgets\BasicStatus;
use NexusPlugin\Tracker\Filament\Widgets\PeerStatus;
use NexusPlugin\Tracker\Filament\Widgets\PprofStatus;
use NexusPlugin\Tracker\Filament\Widgets\RequestStatus;
use NexusPlugin\Tracker\Filament\Widgets\SnatchedStatus;
use NexusPlugin\Tracker\Filament\Widgets\SystemResourceUsage;
use NexusPlugin\Tracker\Filament\Widgets\TorrentStatus;
use NexusPlugin\Tracker\Filament\Widgets\UserStatus;
use NexusPlugin\Tracker\Filament\Widgets\WorkerStatus;
use NexusPlugin\Tracker\Tracker;
use UnitEnum;

class TrackerStatus extends \Filament\Pages\Dashboard
{
//    protected static string $resource = TrackerResource::class;

    protected static string $routePath = 'tracker-status';

    protected static string | UnitEnum | null $navigationGroup = 'Tracker';

    protected static ?int $navigationSort = 99;
    protected string|\Filament\Support\Enums\Width|null $maxContentWidth = 'full';

    protected static ?string $title = 'Go Tracker Status';

//    protected static string $view = 'tracker::tracker-status';
//    protected static string $view = 'filament::pages.dashboard';
//    protected string $view = 'filament-panels::pages.dashboard';

    public function getWidgets(): array
    {
        return Tracker::WIDGETS;
    }

//    protected function getColumns(): int | array
//    {
//        return 2;
//    }
}

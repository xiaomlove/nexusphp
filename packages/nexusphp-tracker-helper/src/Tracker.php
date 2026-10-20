<?php
namespace NexusPlugin\Tracker;

use App\Support\StaticMake;
use App\Support\TranslateWithPluginId;
use Filament\Contracts\Plugin;
use Filament\Panel;
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

class Tracker implements Plugin
{
    use StaticMake;
    use TranslateWithPluginId;

    const ID = "tracker";
    const WIDGETS = [
        BasicStatus::class,
        RequestStatus::class,
        WorkerStatus::class,
//        UserStatus::class,
//        TorrentStatus::class,
        PeerStatus::class,
//        SnatchedStatus::class,
        AgentStatus::class,
        SystemResourceUsage::class,
        PprofStatus::class,
    ];

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                TrackerResource::class,
            ])
            ->widgets(self::WIDGETS)
        ;
    }

    public function boot(Panel $panel): void
    {

    }

}

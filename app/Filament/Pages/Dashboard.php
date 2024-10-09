<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\AccountInfo;
use App\Filament\Widgets\LatestTorrents;
use App\Filament\Widgets\LatestUsers;
use App\Filament\Widgets\SystemInfo;
use App\Filament\Widgets\TorrentStat;
use App\Filament\Widgets\TorrentTrend;
use App\Filament\Widgets\UserClassStat;
use App\Filament\Widgets\UserStat;
use App\Filament\Widgets\UserTrend;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected ?string $maxContentWidth = 'full';

    protected function getWidgets(): array
    {
        return [
            AccountInfo::class,
            LatestUsers::class,
            LatestTorrents::class,
            UserTrend::class,
            TorrentTrend::class,
            UserStat::class,
            UserClassStat::class,
            TorrentStat::class,
            SystemInfo::class,
        ];
    }
}

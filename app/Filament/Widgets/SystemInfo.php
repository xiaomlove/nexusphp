<?php

namespace App\Filament\Widgets;

use App\Filament\Custom\Widgets\StatTable;
use App\Repositories\DashboardRepository;
use Illuminate\Contracts\View\View;
use Nexus\Database\NexusDB;

class SystemInfo extends StatTable
{
    protected static ?int $sort = 1000;

    protected function getHeader(): string
    {
        return nexus_trans('dashboard.system_info.page_title');
    }

    protected function getTableRows(): array
    {
        $dashboardRep = new DashboardRepository();

        return $dashboardRep->getSystemInfo();
    }

}

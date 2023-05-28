<?php

namespace App\Filament\Widgets;

use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\LineChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class UserTrend extends LineChartWidget
{

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('dashboard.user_trend.page_title');
    }

    protected function getData(): array
    {
        $data = Trend::model(User::class)
            ->dateColumn('added')
            ->between(
                start: now()->subDays(30),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => __('label.user.label'),
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('m-d')),
        ];
    }
}

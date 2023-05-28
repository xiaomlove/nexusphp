<?php

namespace App\Filament\Widgets;

use App\Models\Torrent;
use Carbon\Carbon;
use Filament\Widgets\LineChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class TorrentTrend extends LineChartWidget
{
    protected static ?int $sort = 4;

    protected static ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('dashboard.torrent_trend.page_title');
    }

    protected function getData(): array
    {
        $data = Trend::model(Torrent::class)
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
                    'label' => __('label.torrent.label'),
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('m-d')),
        ];
    }
}

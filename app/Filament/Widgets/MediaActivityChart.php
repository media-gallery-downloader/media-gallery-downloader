<?php

namespace App\Filament\Widgets;

use App\Models\Media;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class MediaActivityChart extends ChartWidget
{
    protected static ?string $heading = 'Videos Added (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '150px';

    protected function getData(): array
    {
        // One grouped query for the whole window instead of a COUNT per day.
        // date() matches the semantics of the whereDate() calls this replaced.
        $counts = Media::query()
            ->where('created_at', '>=', Carbon::today()->subDays(29))
            ->selectRaw('date(created_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $data = collect(range(29, 0))->map(function ($daysAgo) use ($counts) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date' => $date->format('M j'),
                'count' => (int) ($counts[$date->toDateString()] ?? 0),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Videos Added',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}

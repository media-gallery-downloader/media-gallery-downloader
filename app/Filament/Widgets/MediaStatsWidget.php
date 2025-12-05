<?php

namespace App\Filament\Widgets;

use App\Helpers\FormatHelper;
use App\Models\Media;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Storage;

class MediaStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalVideos = Media::count();
        $totalSize = Media::sum('size');

        // Storage disk info
        $diskPath = Storage::disk('public')->path('');
        $diskTotal = disk_total_space($diskPath) ?: 0;
        $diskFree = disk_free_space($diskPath) ?: 0;
        $diskUsed = $diskTotal - $diskFree;
        $diskUsedPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // Recent activity
        $todayCount = Media::whereDate('created_at', today())->count();
        $weekCount = Media::where('created_at', '>=', now()->subWeek())->count();

        return [
            Stat::make('Total Videos', number_format($totalVideos))
                ->description(FormatHelper::formatBytes($totalSize).' total storage')
                ->descriptionIcon('heroicon-m-film')
                ->color('primary'),

            Stat::make('Added Today', number_format($todayCount))
                ->description('New videos today')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('This Week', number_format($weekCount))
                ->description('Videos added this week')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Disk Usage', $diskUsedPercent.'%')
                ->description(FormatHelper::formatBytes($diskFree).' free of '.FormatHelper::formatBytes($diskTotal))
                ->descriptionIcon('heroicon-m-server')
                ->color($diskUsedPercent > 90 ? 'danger' : ($diskUsedPercent > 75 ? 'warning' : 'success')),
        ];
    }
}

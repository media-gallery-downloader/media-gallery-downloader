<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class SystemHealthWidget extends Widget
{
    protected static string $view = 'filament.widgets.system-health';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getHealthData(): array
    {
        return Cache::remember('system_health_data', 300, function () {
            return [
                'ytdlp' => $this->getYtdlpInfo(),
                'ffmpeg' => $this->getFfmpegInfo(),
                'php' => $this->getPhpInfo(),
                'disk' => $this->getDiskInfo(),
                'last_runs' => $this->getLastRunTimes(),
            ];
        });
    }

    protected function getYtdlpInfo(): array
    {
        try {
            $process = new Process(['yt-dlp', '--version']);
            $process->setTimeout(5);
            $process->run();

            $currentVersion = $process->isSuccessful() ? trim($process->getOutput()) : null;

            // Check for latest version
            $latestVersion = null;
            try {
                $response = Http::timeout(5)->get('https://api.github.com/repos/yt-dlp/yt-dlp/releases/latest');
                if ($response->successful()) {
                    $latestVersion = ltrim($response->json()['tag_name'] ?? '', 'v');
                }
            } catch (\Exception $e) {
                // Ignore network errors
            }

            $isUpToDate = $currentVersion && $latestVersion
                ? version_compare($currentVersion, $latestVersion, '>=')
                : null;

            return [
                'installed' => $currentVersion !== null,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'is_up_to_date' => $isUpToDate,
            ];
        } catch (\Exception $e) {
            return [
                'installed' => false,
                'current_version' => null,
                'latest_version' => null,
                'is_up_to_date' => null,
            ];
        }
    }

    protected function getFfmpegInfo(): array
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->setTimeout(5);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                preg_match('/ffmpeg version (\S+)/', $output, $matches);

                return [
                    'installed' => true,
                    'version' => $matches[1] ?? 'unknown',
                ];
            }

            return ['installed' => false, 'version' => null];
        } catch (\Exception $e) {
            return ['installed' => false, 'version' => null];
        }
    }

    protected function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
    }

    protected function getDiskInfo(): array
    {
        $storagePath = storage_path();
        $total = disk_total_space($storagePath);
        $free = disk_free_space($storagePath);
        $used = $total - $free;

        return [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'percent_used' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    protected function getLastRunTimes(): array
    {
        return [
            'ytdlp_update' => Cache::get('last_ytdlp_update'),
            'duplicate_removal' => Cache::get('last_duplicate_removal'),
            'storage_cleanup' => Cache::get('last_storage_cleanup'),
            'database_backup' => Cache::get('last_database_backup'),
        ];
    }

    public function refreshHealth(): void
    {
        Cache::forget('system_health_data');
        $this->dispatch('$refresh');
    }

    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}

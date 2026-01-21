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

    // Individual data properties for lazy loading
    public ?array $appData = null;

    public ?array $ytdlpData = null;

    public ?array $ffmpegData = null;

    public ?array $denoData = null;

    public ?array $phpData = null;

    public ?array $diskData = null;

    public ?array $lastRunsData = null;

    public function mount(): void
    {
        // Load fast data immediately
        $this->phpData = $this->getPhpInfo();
        $this->diskData = $this->getDiskInfo();
        $this->lastRunsData = $this->getLastRunTimes();
    }

    public function loadAppData(): void
    {
        $this->appData = Cache::remember('health_app_data', 300, fn () => $this->getAppInfo());
    }

    public function loadYtdlpData(): void
    {
        $this->ytdlpData = Cache::remember('health_ytdlp_data', 300, fn () => $this->getYtdlpInfo());
    }

    public function loadFfmpegData(): void
    {
        $this->ffmpegData = Cache::remember('health_ffmpeg_data', 300, fn () => $this->getFfmpegInfo());
    }

    public function loadDenoData(): void
    {
        $this->denoData = Cache::remember('health_deno_data', 300, fn () => $this->getDenoInfo());
    }

    public function getHealthData(): array
    {
        return Cache::remember('system_health_data', 300, function () {
            return [
                'app' => $this->getAppInfo(),
                'ytdlp' => $this->getYtdlpInfo(),
                'ffmpeg' => $this->getFfmpegInfo(),
                'deno' => $this->getDenoInfo(),
                'php' => $this->getPhpInfo(),
                'disk' => $this->getDiskInfo(),
                'last_runs' => $this->getLastRunTimes(),
            ];
        });
    }

    protected function getAppInfo(): array
    {
        $currentVersion = config('app.version');
        $repository = config('app.repository');

        // Check for latest version from GitHub
        $latestVersion = null;
        $isUpToDate = null;

        try {
            $response = Http::timeout(5)->get($repository.'/releases/latest');
            if ($response->successful()) {
                // Extract version from the redirect URL or page
                $finalUrl = $response->effectiveUri()?->__toString() ?? '';
                if (preg_match('/\/releases\/tag\/v?([\d.]+)/', $finalUrl, $matches)) {
                    $latestVersion = $matches[1];
                }
            }
        } catch (\Exception $e) {
            // Ignore network errors
        }

        if ($currentVersion && $latestVersion) {
            $isUpToDate = version_compare($currentVersion, $latestVersion, '>=');
        }

        return [
            'name' => config('app.name'),
            'version' => $currentVersion,
            'latest_version' => $latestVersion,
            'is_up_to_date' => $isUpToDate,
            'repository' => $repository,
        ];
    }

    protected function getYtdlpInfo(): array
    {
        try {
            $process = new Process(['yt-dlp', '--version']);
            $process->setTimeout(5);
            $process->run();

            $currentVersion = $process->isSuccessful() ? trim($process->getOutput()) : null;

            // Check for latest nightly version
            $latestVersion = null;
            try {
                $response = Http::timeout(5)->get('https://api.github.com/repos/yt-dlp/yt-dlp-nightly-builds/releases/latest');
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

    protected function getDenoInfo(): array
    {
        try {
            $process = new Process(['deno', '--version']);
            $process->setTimeout(5);
            $process->run();

            $currentVersion = null;
            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                // Output format: "deno 2.5.6 (release, x86_64-unknown-linux-gnu)\nv8 13.0.245.12-rusty\ntypescript 5.6.2"
                if (preg_match('/deno (\S+)/', $output, $matches)) {
                    $currentVersion = $matches[1];
                }
            }

            // Check for latest version
            $latestVersion = null;
            try {
                $response = Http::timeout(5)->get('https://api.github.com/repos/denoland/deno/releases/latest');
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
            'deno_update' => Cache::get('last_deno_update'),
            'duplicate_removal' => Cache::get('last_duplicate_removal'),
            'storage_cleanup' => Cache::get('last_storage_cleanup'),
            'database_backup' => Cache::get('last_database_backup'),
        ];
    }

    public function refreshHealth(): void
    {
        Cache::forget('system_health_data');
        Cache::forget('health_app_data');
        Cache::forget('health_ytdlp_data');
        Cache::forget('health_ffmpeg_data');
        Cache::forget('health_deno_data');

        // Reset all data to trigger reload
        $this->appData = null;
        $this->ytdlpData = null;
        $this->ffmpegData = null;
        $this->denoData = null;

        // Reload fast data immediately
        $this->phpData = $this->getPhpInfo();
        $this->diskData = $this->getDiskInfo();
        $this->lastRunsData = $this->getLastRunTimes();

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

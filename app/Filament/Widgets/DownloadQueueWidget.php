<?php

namespace App\Filament\Widgets;

use App\Services\DownloadService;
use Filament\Widgets\Widget;

class DownloadQueueWidget extends Widget
{
    protected static string $view = 'filament.widgets.download-queue';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '5s';

    public function getQueueData(): array
    {
        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        $pending = collect($queue)->where('status', 'queued')->count();
        $downloading = collect($queue)->where('status', 'downloading')->count();
        $completed = collect($queue)->where('status', 'completed')->count();
        $failed = collect($queue)->where('status', 'failed')->count();

        return [
            'items' => array_slice($queue, 0, 10), // Show last 10 items
            'stats' => [
                'pending' => $pending,
                'downloading' => $downloading,
                'completed' => $completed,
                'failed' => $failed,
                'total' => count($queue),
            ],
        ];
    }

    public function clearCompleted(): void
    {
        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        foreach ($queue as $item) {
            if ($item['status'] === 'completed') {
                $downloadService->removeFromQueue($item['id']);
            }
        }

        $this->dispatch('$refresh');
    }

    public function retryFailed(string $downloadId): void
    {
        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        $item = collect($queue)->firstWhere('id', $downloadId);

        if ($item && $item['status'] === 'failed') {
            $downloadService->removeFromQueue($downloadId);
            $downloadService->downloadFromUrl($item['url'], uniqid('retry_'));
        }

        $this->dispatch('$refresh');
    }
}

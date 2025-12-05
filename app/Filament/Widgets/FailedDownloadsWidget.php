<?php

namespace App\Filament\Widgets;

use App\Models\FailedDownload;
use App\Services\DownloadService;
use App\Services\MaintenanceService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class FailedDownloadsWidget extends Widget
{
    protected static string $view = 'filament.widgets.failed-downloads';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function getFailedDownloads(): array
    {
        return FailedDownload::orderByDesc('created_at')
            ->take(20)
            ->get()
            ->toArray();
    }

    public function getStats(): array
    {
        return [
            'pending' => FailedDownload::where('status', 'pending')->count(),
            'retrying' => FailedDownload::where('status', 'retrying')->count(),
            'failed' => FailedDownload::where('status', 'failed')->count(),
            'resolved' => FailedDownload::where('status', 'resolved')->count(),
        ];
    }

    public function retryDownload(int $id): void
    {
        $failed = FailedDownload::find($id);
        if (! $failed) {
            return;
        }

        $downloadService = app(DownloadService::class);

        $failed->markRetrying();

        try {
            $downloadService->downloadFromUrl($failed->url, uniqid('retry_'));
            $failed->markResolved();

            Notification::make()
                ->title('Download retried')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $failed->markFailed($e->getMessage());

            Notification::make()
                ->title('Retry failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->dispatch('$refresh');
    }

    public function deleteDownload(int $id): void
    {
        FailedDownload::destroy($id);
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Failed download removed')
            ->success()
            ->send();
    }

    public function clearResolved(): void
    {
        FailedDownload::where('status', 'resolved')->delete();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Resolved downloads cleared')
            ->success()
            ->send();
    }

    public function clearAllFailed(): void
    {
        FailedDownload::where('status', 'failed')->delete();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Failed downloads cleared')
            ->success()
            ->send();
    }

    public function retryAllPending(): void
    {
        $service = app(MaintenanceService::class);
        $count = $service->retryFailedDownloads();

        Notification::make()
            ->title("Retrying {$count} downloads")
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }
}

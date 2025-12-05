@php
$data = $this->getHealthData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>System Health</span>
                <x-filament::button
                    wire:click="refreshHealth"
                    size="sm"
                    icon="heroicon-m-arrow-path"
                    color="gray">
                    Refresh
                </x-filament::button>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- yt-dlp Status --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center gap-2 mb-2">
                    @if($data['ytdlp']['installed'])
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    @else
                    <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500" />
                    @endif
                    <span class="font-medium text-gray-900 dark:text-white">yt-dlp</span>
                </div>
                @if($data['ytdlp']['installed'])
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Version: {{ $data['ytdlp']['current_version'] }}</div>
                    @if($data['ytdlp']['latest_version'])
                    <div>Latest: {{ $data['ytdlp']['latest_version'] }}</div>
                    @if($data['ytdlp']['is_up_to_date'])
                    <span class="text-success-500">✓ Up to date</span>
                    @else
                    <span class="text-warning-500">Update available</span>
                    @endif
                    @endif
                </div>
                @else
                <div class="text-sm text-danger-500">Not installed</div>
                @endif
            </div>

            {{-- FFmpeg Status --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center gap-2 mb-2">
                    @if($data['ffmpeg']['installed'])
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    @else
                    <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500" />
                    @endif
                    <span class="font-medium text-gray-900 dark:text-white">FFmpeg</span>
                </div>
                @if($data['ffmpeg']['installed'])
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Version: {{ $data['ffmpeg']['version'] }}
                </div>
                @else
                <div class="text-sm text-danger-500">Not installed</div>
                @endif
            </div>

            {{-- PHP Info --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    <span class="font-medium text-gray-900 dark:text-white">PHP</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Version: {{ $data['php']['version'] }}</div>
                    <div>Memory: {{ $data['php']['memory_limit'] }}</div>
                    <div>Upload: {{ $data['php']['upload_max_filesize'] }}</div>
                </div>
            </div>

            {{-- Disk Space --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center gap-2 mb-2">
                    @php
                    $diskPercent = $data['disk']['percent_used'];
                    $diskColor = $diskPercent > 90 ? 'danger' : ($diskPercent > 75 ? 'warning' : 'success');
                    @endphp
                    <x-dynamic-component :component="'heroicon-s-' . ($diskPercent > 90 ? 'exclamation-circle' : 'server')"
                        class="w-5 h-5 text-{{ $diskColor }}-500" />
                    <span class="font-medium text-gray-900 dark:text-white">Disk Space</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Used: {{ number_format($data['disk']['percent_used'], 1) }}%</div>
                    <div>Free: {{ $this->formatBytes($data['disk']['free']) }}</div>
                    <div>Total: {{ $this->formatBytes($data['disk']['total']) }}</div>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                    @php
                    $diskBarClass = match($diskColor) {
                    'red' => 'bg-red-500',
                    'yellow' => 'bg-yellow-500',
                    default => 'bg-green-500',
                    };
                    @endphp
                    <div class="{{ $diskBarClass }} h-2 rounded-full" style="width: {{ $diskPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Last Run Times --}}
        @if(array_filter($data['last_runs']))
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Last Maintenance Runs</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                @if($data['last_runs']['ytdlp_update'])
                <div>
                    <span class="text-gray-500 dark:text-gray-400">yt-dlp Update:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($data['last_runs']['ytdlp_update'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($data['last_runs']['duplicate_removal'])
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Duplicate Removal:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($data['last_runs']['duplicate_removal'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($data['last_runs']['storage_cleanup'])
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Storage Cleanup:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($data['last_runs']['storage_cleanup'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($data['last_runs']['database_backup'])
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Database Backup:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($data['last_runs']['database_backup'])->diffForHumans() }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
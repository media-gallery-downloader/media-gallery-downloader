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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            {{-- App Info --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800" wire:init="loadAppData">
                @if($appData)
                <div class="flex items-center gap-2 mb-2">
                    @if($appData['is_up_to_date'] === true)
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    @elseif($appData['is_up_to_date'] === false)
                    <x-heroicon-s-arrow-up-circle class="w-5 h-5 text-warning-500" />
                    @else
                    <x-heroicon-s-cube class="w-5 h-5 text-gray-500" />
                    @endif
                    <span class="font-medium text-gray-900 dark:text-white">{{ $appData['name'] }}</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Version: {{ $appData['version'] }}</div>
                    @if($appData['latest_version'])
                    <div>Latest: {{ $appData['latest_version'] }}</div>
                    @if($appData['is_up_to_date'])
                    <span class="text-success-500">✓ Up to date</span>
                    @else
                    <span class="text-warning-500">Update available</span>
                    @endif
                    @endif
                    @if($appData['repository'])
                    <div class="mt-1">
                        <a href="{{ $appData['repository'] }}" target="_blank" rel="noopener noreferrer" class="text-primary-500 hover:text-primary-600 inline-flex items-center gap-1">
                            <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3" />
                            GitHub
                        </a>
                    </div>
                    @endif
                </div>
                @else
                <div class="animate-pulse">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-12"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- yt-dlp Status --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800" wire:init="loadYtdlpData">
                @if($ytdlpData)
                <div class="flex items-center gap-2 mb-2">
                    @if($ytdlpData['installed'])
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    @else
                    <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500" />
                    @endif
                    <span class="font-medium text-gray-900 dark:text-white">yt-dlp</span>
                </div>
                @if($ytdlpData['installed'])
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Version: {{ $ytdlpData['current_version'] }}</div>
                    @if($ytdlpData['latest_version'])
                    <div>Latest: {{ $ytdlpData['latest_version'] }}</div>
                    @if($ytdlpData['is_up_to_date'])
                    <span class="text-success-500">✓ Up to date</span>
                    @else
                    <span class="text-warning-500">Update available</span>
                    @endif
                    @endif
                </div>
                @else
                <div class="text-sm text-danger-500">Not installed</div>
                @endif
                @else
                <div class="animate-pulse">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-12"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-24"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- FFmpeg Status --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800" wire:init="loadFfmpegData">
                @if($ffmpegData)
                <div class="flex items-center gap-2 mb-2">
                    @if($ffmpegData['installed'])
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    @else
                    <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500" />
                    @endif
                    <span class="font-medium text-gray-900 dark:text-white">FFmpeg</span>
                </div>
                @if($ffmpegData['installed'])
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Version: {{ $ffmpegData['version'] }}
                </div>
                @else
                <div class="text-sm text-danger-500">Not installed</div>
                @endif
                @else
                <div class="animate-pulse">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-14"></div>
                    </div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-28"></div>
                </div>
                @endif
            </div>

            {{-- Deno Status --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800" wire:init="loadDenoData">
                @if($denoData)
                <div class="flex items-center gap-2 mb-2">
                    @if($denoData['installed'])
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    @else
                    <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500" />
                    @endif
                    <span class="font-medium text-gray-900 dark:text-white">Deno</span>
                </div>
                @if($denoData['installed'])
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Version: {{ $denoData['current_version'] }}</div>
                    @if($denoData['latest_version'])
                    <div>Latest: {{ $denoData['latest_version'] }}</div>
                    @if($denoData['is_up_to_date'])
                    <span class="text-success-500">✓ Up to date</span>
                    @else
                    <span class="text-warning-500">Update available</span>
                    @endif
                    @endif
                </div>
                @else
                <div class="text-sm text-danger-500">Not installed</div>
                @endif
                @else
                <div class="animate-pulse">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-10"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- PHP Info --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                @if($phpData)
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                    <span class="font-medium text-gray-900 dark:text-white">PHP</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Version: {{ $phpData['version'] }}</div>
                    <div>Memory: {{ $phpData['memory_limit'] }}</div>
                    <div>Upload: {{ $phpData['upload_max_filesize'] }}</div>
                </div>
                @else
                <div class="animate-pulse">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-8"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Disk Space --}}
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                @if($diskData)
                <div class="flex items-center gap-2 mb-2">
                    @php
                    $diskPercent = $diskData['percent_used'];
                    $diskColor = $diskPercent > 90 ? 'danger' : ($diskPercent > 75 ? 'warning' : 'success');
                    @endphp
                    <x-dynamic-component :component="'heroicon-s-' . ($diskPercent > 90 ? 'exclamation-circle' : 'server')"
                        class="w-5 h-5 text-{{ $diskColor }}-500" />
                    <span class="font-medium text-gray-900 dark:text-white">Disk Space</span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Used: {{ number_format($diskData['percent_used'], 1) }}%</div>
                    <div>Free: {{ $this->formatBytes($diskData['free']) }}</div>
                    <div>Total: {{ $this->formatBytes($diskData['total']) }}</div>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                    @php
                    $diskBarClass = match($diskColor) {
                    'danger' => 'bg-red-500',
                    'warning' => 'bg-yellow-500',
                    default => 'bg-green-500',
                    };
                    @endphp
                    <div class="{{ $diskBarClass }} h-2 rounded-full" style="width: {{ $diskPercent }}%"></div>
                </div>
                @else
                <div class="animate-pulse">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-5 h-5 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                        <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-16"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                    </div>
                    <div class="mt-2 w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700"></div>
                </div>
                @endif
            </div>
        </div>

        {{-- Last Run Times --}}
        @if($lastRunsData && array_filter($lastRunsData))
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Last Maintenance Runs</h4>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                @if($lastRunsData['ytdlp_update'] ?? null)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">yt-dlp Update:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($lastRunsData['ytdlp_update'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($lastRunsData['deno_update'] ?? null)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Deno Update:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($lastRunsData['deno_update'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($lastRunsData['duplicate_removal'] ?? null)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Duplicate Removal:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($lastRunsData['duplicate_removal'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($lastRunsData['storage_cleanup'] ?? null)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Storage Cleanup:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($lastRunsData['storage_cleanup'])->diffForHumans() }}</div>
                </div>
                @endif
                @if($lastRunsData['database_backup'] ?? null)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Database Backup:</span>
                    <div class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($lastRunsData['database_backup'])->diffForHumans() }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

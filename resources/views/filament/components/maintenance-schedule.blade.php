<div x-data="{
    ytdlpHour: '12',
    ytdlpMinute: '00',
    ytdlpPeriod: 'AM',
    denoHour: '12',
    denoMinute: '00',
    denoPeriod: 'AM',
    duplicatesHour: '12',
    duplicatesMinute: '00',
    duplicatesPeriod: 'AM',
    storageHour: '12',
    storageMinute: '00',
    storagePeriod: 'AM',
    backupHour: '12',
    backupMinute: '00',
    backupPeriod: 'AM',
    logRotationHour: '12',
    logRotationMinute: '00',
    logRotationPeriod: 'AM',
    thumbnailHour: '12',
    thumbnailMinute: '00',
    thumbnailPeriod: 'AM',
    importHour: '12',
    importMinute: '00',
    importPeriod: 'AM',

    init() {
        this.parseTime('ytdlp', $wire.data?.ytdlp_schedule_time);
        this.parseTime('deno', $wire.data?.deno_schedule_time);
        this.parseTime('duplicates', $wire.data?.duplicates_schedule_time);
        this.parseTime('storage', $wire.data?.storage_cleanup_schedule_time);
        this.parseTime('backup', $wire.data?.database_backup_schedule_time);
        this.parseTime('logRotation', $wire.data?.log_rotation_schedule_time);
        this.parseTime('thumbnail', $wire.data?.thumbnail_regen_schedule_time);
        this.parseTime('import', $wire.data?.import_scan_schedule_time);

        $watch('ytdlpHour', () => this.updateTime('ytdlp'));
        $watch('ytdlpMinute', () => this.updateTime('ytdlp'));
        $watch('ytdlpPeriod', () => this.updateTime('ytdlp'));
        $watch('denoHour', () => this.updateTime('deno'));
        $watch('denoMinute', () => this.updateTime('deno'));
        $watch('denoPeriod', () => this.updateTime('deno'));
        $watch('duplicatesHour', () => this.updateTime('duplicates'));
        $watch('duplicatesMinute', () => this.updateTime('duplicates'));
        $watch('duplicatesPeriod', () => this.updateTime('duplicates'));
        $watch('storageHour', () => this.updateTime('storage'));
        $watch('storageMinute', () => this.updateTime('storage'));
        $watch('storagePeriod', () => this.updateTime('storage'));
        $watch('backupHour', () => this.updateTime('backup'));
        $watch('backupMinute', () => this.updateTime('backup'));
        $watch('backupPeriod', () => this.updateTime('backup'));
        $watch('logRotationHour', () => this.updateTime('logRotation'));
        $watch('logRotationMinute', () => this.updateTime('logRotation'));
        $watch('logRotationPeriod', () => this.updateTime('logRotation'));
        $watch('thumbnailHour', () => this.updateTime('thumbnail'));
        $watch('thumbnailMinute', () => this.updateTime('thumbnail'));
        $watch('thumbnailPeriod', () => this.updateTime('thumbnail'));
        $watch('importHour', () => this.updateTime('import'));
        $watch('importMinute', () => this.updateTime('import'));
        $watch('importPeriod', () => this.updateTime('import'));
    },

    parseTime(prefix, time) {
        if (!time) return;
        const [hours, minutes] = time.split(':');
        let h = parseInt(hours);
        const period = h >= 12 ? 'PM' : 'AM';
        if (h === 0) h = 12;
        else if (h > 12) h -= 12;

        this[prefix + 'Hour'] = h.toString();
        this[prefix + 'Minute'] = minutes;
        this[prefix + 'Period'] = period;
    },

    updateTime(prefix) {
        let h = parseInt(this[prefix + 'Hour']);
        const m = this[prefix + 'Minute'];
        const p = this[prefix + 'Period'];

        if (p === 'PM' && h !== 12) h += 12;
        if (p === 'AM' && h === 12) h = 0;

        const time = h.toString().padStart(2, '0') + ':' + m;

        const timeFieldMap = {
            'ytdlp': 'ytdlp_schedule_time',
            'deno': 'deno_schedule_time',
            'duplicates': 'duplicates_schedule_time',
            'storage': 'storage_cleanup_schedule_time',
            'backup': 'database_backup_schedule_time',
            'logRotation': 'log_rotation_schedule_time',
            'thumbnail': 'thumbnail_regen_schedule_time',
            'import': 'import_scan_schedule_time'
        };

        $wire.set('data.' + timeFieldMap[prefix], time);
    }
}">
    <table class="w-full">
        <thead>
            <tr>
                <th class="text-left pb-4" style="width: 220px;"></th>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <th class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 pb-4" style="width: 50px;">{{ $day }}</th>
                @endforeach
                <th class="pb-4" style="width: 150px;"></th>
                <th class="pb-4"></th>
            </tr>
        </thead>
        <tbody>
            {{-- Bulk Import Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Bulk import scan</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Scan incoming directory and import videos.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.import_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="importHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="importMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="importPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveImportSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runImportScan" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- Database Backup Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Database backup</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Create automatic database backups.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.backup_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="backupHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="backupMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="backupPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1 items-center flex-wrap">
                        <x-filament::button wire:click="saveBackupSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runBackup" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                        <x-filament::button
                            x-on:click="$dispatch('open-modal', { id: 'download-backups-modal' })"
                            icon="heroicon-m-arrow-down-tray"
                            color="gray"
                            size="xs"
                            tooltip="Download Backups"
                        >
                            Download
                        </x-filament::button>
                        <x-filament::button
                            x-on:click="$dispatch('open-modal', { id: 'restore-backup-modal' })"
                            icon="heroicon-m-arrow-up-tray"
                            color="warning"
                            size="xs"
                            tooltip="Restore from Backup"
                        >
                            Restore
                        </x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- Duplicate Removal Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Duplicate removal</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Remove duplicate media files.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.duplicates_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="duplicatesHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="duplicatesMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="duplicatesPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveDuplicatesSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runDuplicateRemoval" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- Log Rotation Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Log rotation</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Clean up old log files.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.log_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="logRotationHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="logRotationMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="logRotationPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveLogRotationSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runLogRotation" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- Storage Cleanup Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Storage cleanup</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Remove orphaned files from storage.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.storage_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="storageHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="storageMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="storagePeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveStorageSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runStorageCleanup" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- Thumbnail Regeneration Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Thumbnail regeneration</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Regenerate thumbnails for videos/GIFs.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.thumbnail_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="thumbnailHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="thumbnailMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="thumbnailPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveThumbnailSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runThumbnailRegeneration" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- yt-dlp Update Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">yt-dlp update</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Schedule automatic updates for yt-dlp.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.ytdlp_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="ytdlpHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="ytdlpMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="ytdlpPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveYtdlpSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runYtdlpUpdate" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>

            {{-- Deno Update Row --}}
            <tr>
                <td class="align-middle py-3">
                    <div class="font-medium text-gray-950 dark:text-white">Deno update</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Schedule automatic updates for Deno runtime.</div>
                </td>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <td class="text-center align-middle py-3">
                    <input type="checkbox"
                        wire:model="data.deno_day_{{ $day }}"
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                </td>
                @endforeach
                <td class="align-middle py-3 px-2">
                    <div class="flex items-center gap-1">
                        <select x-model="denoHour" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                        </select>
                        <span class="text-gray-500 dark:text-gray-400">:</span>
                        <select x-model="denoMinute" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(['00', '15', '30', '45'] as $min)
                            <option value="{{ $min }}">{{ $min }}</option>
                            @endforeach
                        </select>
                        <select x-model="denoPeriod" class="fi-select-input block w-16 py-2 px-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </td>
                <td class="align-middle py-3">
                    <div class="flex gap-1">
                        <x-filament::button wire:click="saveDenoSchedule" icon="heroicon-m-check" color="success" size="xs">Save</x-filament::button>
                        <x-filament::button wire:click="runDenoUpdate" icon="heroicon-m-play" size="xs">Run Now</x-filament::button>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Download Backups Modal --}}
    <x-filament::modal id="download-backups-modal" width="lg">
        <x-slot name="heading">
            Download Backups
        </x-slot>

        <x-slot name="description">
            Select a backup file to download.
        </x-slot>

        @php
            $backups = $this->getBackupFiles();
        @endphp

        @if(empty($backups))
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-archive-box class="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>No backups found.</p>
                <p class="text-sm mt-1">Run a backup to create one.</p>
            </div>
        @else
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($backups as $backup)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $backup['name'] }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $backup['size'] }} • {{ $backup['date'] }}</div>
                        </div>
                        <a
                            href="{{ route('backup.download', ['filename' => $backup['name']]) }}"
                            download
                            class="fi-btn fi-btn-size-sm relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-sm gap-1 px-2.5 py-1.5 text-sm inline-grid shadow-sm bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400"
                        >
                            <svg class="fi-btn-icon h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" />
                                <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                            </svg>
                            <span class="fi-btn-label">Download</span>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::modal>

    {{-- Restore Backup Modal --}}
    <x-filament::modal id="restore-backup-modal" width="lg">
        <x-slot name="heading">
            Restore from Backup
        </x-slot>

        <x-slot name="description">
            Upload a backup file to restore media records. Duplicate records will be skipped.
        </x-slot>

        <div class="space-y-4">
            <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20">
                <div class="flex gap-2">
                    <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-warning-600 dark:text-warning-400 flex-shrink-0" />
                    <div class="text-sm text-warning-700 dark:text-warning-300">
                        <p class="font-medium">Before restoring:</p>
                        <ul class="list-disc ml-4 mt-1 space-y-1">
                            <li>Existing records with matching name+size will be skipped</li>
                            <li>Records with source URLs but missing files will be queued for re-download</li>
                            <li>This process cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div wire:ignore>
                <input
                    type="file"
                    accept=".sqlite,.sql,.db"
                    class="block w-full text-sm text-gray-500 dark:text-gray-400
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-lg file:border-0
                        file:text-sm file:font-medium
                        file:bg-primary-50 file:text-primary-700
                        dark:file:bg-primary-500/10 dark:file:text-primary-400
                        hover:file:bg-primary-100 dark:hover:file:bg-primary-500/20
                        cursor-pointer"
                    x-on:change="
                        const file = $event.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                // Convert ArrayBuffer to Base64 for binary-safe transfer
                                const bytes = new Uint8Array(e.target.result);
                                let binary = '';
                                for (let i = 0; i < bytes.byteLength; i++) {
                                    binary += String.fromCharCode(bytes[i]);
                                }
                                const base64 = btoa(binary);
                                $wire.restoreBackup(base64, true);
                            };
                            reader.readAsArrayBuffer(file);
                        }
                    "
                />
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Supported formats: SQLite database files (.sqlite, .db) or SQL dump files (.sql)
            </p>
        </div>
    </x-filament::modal>
</div>

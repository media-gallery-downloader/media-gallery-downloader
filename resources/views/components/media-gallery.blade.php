@php
$sortOptions = [
'newest' => 'Newest First',
'oldest' => 'Oldest First',
'name_asc' => 'Name (A-Z)',
'name_desc' => 'Name (Z-A)',
'size_asc' => 'Size (Smallest)',
'size_desc' => 'Size (Largest)',
];

$sort = request()->query('sort', 'newest');
$perPage = request()->query('per_page', 10);

// Build the query based on sort parameter
$query = \App\Models\Media::query();

switch($sort) {
case 'newest':
$query->latest();
break;
case 'oldest':
$query->oldest();
break;
case 'name_asc':
$query->orderBy('name', 'asc');
break;
case 'name_desc':
$query->orderBy('name', 'desc');
break;
case 'size_asc':
$query->orderBy('size', 'asc');
break;
case 'size_desc':
$query->orderBy('size', 'desc');
break;
default:
$query->latest();
}

$media = $query->paginate($perPage);
@endphp

<div class="space-y-4 pt-0 pb-2 px-2 container mx-auto">
    <!-- Controls Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 p-2 rounded-lg shadow bg-white dark:bg-gray-800 z-10">
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-700 dark:text-gray-200">Sort by:</span>
            <select
                x-data="{}"
                x-on:change="window.location = `?sort=${$event.target.value}&per_page={{ $perPage }}`"
                class="text-xs text-black dark:text-white rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @foreach($sortOptions as $value => $label)
                <option value="{{ $value }}" {{ $sort === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-700 dark:text-gray-200">Show:</span>
            <select
                x-data="{}"
                x-on:change="window.location = `?sort={{ $sort }}&per_page=${$event.target.value}`"
                class="text-xs text-black dark:text-white rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @foreach([10, 20, 50, 100] as $value)
                <option value="{{ $value }}" {{ $perPage == $value ? 'selected' : '' }}>
                    {{ $value }}
                </option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Gallery Grid - Flexbox Approach -->
    <div class="w-full overflow-hidden">
        <div class="flex flex-wrap" style="margin: -4px;">
            @forelse($media as $item)
            <div
                class="relative cursor-pointer p-0 overflow-hidden rounded-lg shadow"
                style="width: calc(20% - 8px); margin: 4px; aspect-ratio: 1/1;"
                x-data=""
                x-on:click="$dispatch('open-media-preview', { 
                url: '{{ $item->url }}', 
                name: '{{ $item->name }}',
                type: '{{ $item->mime_type }}'
            })">
                <!-- Info overlays -->
                <div class="p-1" style="position:absolute;top:0;background:black;margin-right:0px;margin-left:0px;width:100%;opacity:0.8;z-index:15;">
                    <div class="group relative flex overflow-hidden max-w-[60%]"
                        x-data="{ 
            shouldScroll: false,
            init() {
                this.checkOverflow();
                window.addEventListener('resize', () => this.checkOverflow());
            },
            checkOverflow() {
                const el = this.$refs.titleText;
                this.shouldScroll = el.scrollWidth > el.clientWidth;
            }
         }">
                        <span
                            x-ref="titleText"
                            class="text-xs text-white whitespace-nowrap transition-all duration-1000 ease-in-out"
                            :class="{ 'hover:translate-x-[max(calc(60%-100%),calc(-100%+60px))]': shouldScroll }"
                            title="{{ $item->name }}">
                            {{ $item->name }}
                        </span>
                    </div>
                </div>
                <div class="p-1" style="position:absolute;bottom:0;background:black;margin-right:0px;margin-left:0px;width:100%;opacity:0.8;z-index:15;">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-white truncate max-w-[60%]">{{ $item->created_at->toDayDateTimeString() }}</span>
                        <div class="flex space-x-1">
                            <a
                                href="#"
                                @click.stop.prevent="$dispatch('open-info-modal', { 
                                id: {{ $item->id }},
                                name: '{{ $item->name }}',
                                file_name: '{{ $item->file_name }}',
                                mime_type: '{{ $item->mime_type }}',
                                size: {{ $item->size }},
                                url: '{{ $item->url }}',
                                created_at: '{{ $item->created_at }}',
                                path: '{{ $item->path ?? '' }}',
                                source: '{{ $item->source ?? '' }}'
                            })"
                                class="text-blue-400 hover:text-blue-300">
                                <x-heroicon-m-information-circle class="w-5 h-5" />
                            </a>
                            <button
                                type="button"
                                x-data=""
                                @click.stop.prevent="$dispatch('open-delete-modal', { 
                                id: {{ $item->id }},
                                name: '{{ $item->name }}'
                            })"
                                class="text-red-400 hover:text-red-300">
                                <x-heroicon-m-trash class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Content container inside your gallery grid item -->
                <div class="absolute inset-0 bg-gray-200 dark:bg-gray-700">
                    @if(Str::contains($item->mime_type, 'video'))
                    <!-- Video thumbnail with hover-to-play -->
                    <div class="absolute inset-0"
                        x-data="{ 
        isHovering: false,
        init() {
            this.$refs.videoElement.addEventListener('loadeddata', () => {
                // Pause immediately after load
                this.$refs.videoElement.pause();
            });
        }
     }"
                        @mouseenter="isHovering = true; $refs.videoElement.play().catch(e => console.log('Could not play video:', e))"
                        @mouseleave="isHovering = false; $refs.videoElement.pause()">
                        <video x-ref="videoElement" class="w-full h-full object-cover" muted @click.stop preload="metadata">
                            <source src="{{ $item->url }}" type="{{ $item->mime_type }}">
                        </video>
                        <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30"
                            :class="{'opacity-0': isHovering, 'opacity-100': !isHovering}"
                            class="transition-opacity duration-300">
                            <svg class="w-6 h-6 text-white opacity-70" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>

                    @elseif(Str::contains($item->mime_type, 'image') && Str::contains($item->file_name, '.gif'))
                    <!-- Animated GIF with hover-to-play -->
                    <div class="absolute inset-0"
                        x-data="{ 
        isHovering: false,
        staticUrl: null,
        animatedUrl: '{{ $item->url }}',
        init() {
            this.createStaticImage('{{ $item->url }}');
        },
        createStaticImage(url) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                canvas.getContext('2d').drawImage(img, 0, 0, img.width, img.height);
                this.staticUrl = canvas.toDataURL('image/png');
            };
            img.src = url;
        }
    }"
                        @mouseenter="isHovering = true"
                        @mouseleave="isHovering = false">

                        <!-- Static preview -->
                        <template x-if="staticUrl && !isHovering">
                            <img
                                :src="staticUrl"
                                alt="{{ $item->file_name }}"
                                class="w-full h-full object-cover">
                        </template>

                        <!-- Fallback while static image generates -->
                        <template x-if="!staticUrl && !isHovering">
                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </template>

                        <!-- Animated version -->
                        <div x-show="isHovering" class="w-full h-full">
                            <img
                                :src="animatedUrl"
                                alt="{{ $item->file_name }}"
                                class="w-full h-full object-cover">
                        </div>

                        <!-- Play indicator -->
                        <div class="absolute inset-0 flex items-center justify-center" x-show="!isHovering">
                            <div class="bg-black bg-opacity-30 rounded-full p-2">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    @elseif(Str::contains($item->mime_type, 'image'))
                    <!-- Regular image thumbnail - no change needed -->
                    <div class="absolute inset-0">
                        <img src="{{ $item->url }}" alt="{{ $item->file_name }}" class="w-full h-full object-cover">
                    </div>

                    @elseif(Str::contains($item->mime_type, 'audio'))
                    <!-- Audio thumbnail -->
                    <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600">
                        <svg class="w-16 h-16 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"></path>
                        </svg>
                        <div class="absolute bottom-2 left-0 right-0 text-center">
                            <span class="text-xs text-white font-medium px-2 py-1 bg-black bg-opacity-50 rounded-full">
                                {{ Str::limit($item->name, 15) }}
                            </span>
                        </div>
                    </div>

                    @elseif(Str::contains($item->mime_type, 'pdf'))
                    <!-- PDF Document thumbnail -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-red-500 to-red-700">
                        <svg class="w-16 h-16 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs text-white font-medium mt-2 px-2 py-1 bg-black bg-opacity-50 rounded-full">PDF</span>
                    </div>

                    @elseif(Str::contains($item->mime_type, ['word', 'document', 'msword', 'officedocument.wordprocessingml']))
                    <!-- Word Document thumbnail -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-blue-600 to-blue-800">
                        <svg class="w-16 h-16 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs text-white font-medium mt-2 px-2 py-1 bg-black bg-opacity-50 rounded-full">DOC</span>
                    </div>

                    @elseif(Str::contains($item->mime_type, ['excel', 'spreadsheet', 'sheet', 'officedocument.spreadsheetml']))
                    <!-- Excel Document thumbnail -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-green-600 to-green-800">
                        <svg class="w-16 h-16 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs text-white font-medium mt-2 px-2 py-1 bg-black bg-opacity-50 rounded-full">XLS</span>
                    </div>

                    @else
                    <!-- Generic File thumbnail for other types -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-gray-500 to-gray-700">
                        <svg class="w-16 h-16 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-xs text-white font-medium mt-2 px-2 py-1 bg-black bg-opacity-50 rounded-full">
                            {{ Str::upper(pathinfo($item->file_name, PATHINFO_EXTENSION)) ?: 'FILE' }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="w-full p-4 text-center">
                <p class="text-gray-500 dark:text-gray-400">No media files found</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Pagination Links -->
    <div class="mt-6">
        {{ $media->appends(['sort' => $sort, 'per_page' => $perPage])->links() }}
    </div>
</div>

<!-- Modal Components -->
<x-modals.media-preview />
<x-modals.media-info />
<x-modals.delete-confirmation />
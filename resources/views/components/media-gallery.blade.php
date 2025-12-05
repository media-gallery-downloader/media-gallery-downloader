@php
use Illuminate\Support\Js;

$sortOptions = [
'newest' => 'Newest First',
'oldest' => 'Oldest First',
'name_asc' => 'Name (A-Z)',
'name_desc' => 'Name (Z-A)',
'size_asc' => 'Size (Smallest)',
'size_desc' => 'Size (Largest)',
];

$sort = $sort ?? request()->query('sort', 'newest');
$perPage = $perPage ?? request()->query('per_page', 10);

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
                wire:key="media-{{ $item->id }}-{{ $item->updated_at->timestamp }}"
                class="relative cursor-pointer p-0 overflow-hidden rounded-lg shadow"
                style="width: calc(20% - 8px); margin: 4px; aspect-ratio: 1/1;"
                data-media-url="/admin/view/{{ $item->id }}"
                x-data="{ cardMenuOpen: false, overCard: false, overPopup: false }"
                @mouseenter="overCard = true"
                @mouseleave="overCard = false; setTimeout(() => { if (!overCard && !overPopup) cardMenuOpen = false }, 50)"
                x-on:click="$dispatch('open-media-preview', {
                    url: '{{ $item->url }}', 
                    name: {{ Js::from($item->name) }}, 
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
                                    name: {{ Js::from($item->name) }}, 
                                    file_name: {{ Js::from($item->file_name) }}, 
                                    mime_type: '{{ $item->mime_type }}', 
                                    size: {{ $item->size }}, 
                                    url: '{{ $item->url }}', 
                                    created_at: '{{ $item->created_at }}', 
                                    path: {{ Js::from($item->path ?? '') }}, 
                                    source: {{ Js::from($item->source ?? '') }}
                                })"
                                class="text-blue-400 hover:text-blue-300">
                                <x-heroicon-m-information-circle class="w-5 h-5" />
                            </a>
                            <!-- Hamburger menu -->
                            <div class="relative" x-data="{
                                menuPosition: { top: 0, left: 0 },
                                updatePosition() {
                                    const rect = this.$refs.menuBtn.getBoundingClientRect();
                                    this.menuPosition = {
                                        top: rect.top - 8,
                                        left: rect.right - 144
                                    };
                                }
                            }" @click.stop>
                                <button
                                    type="button"
                                    x-ref="menuBtn"
                                    @click.prevent="updatePosition(); cardMenuOpen = !cardMenuOpen"
                                    class="text-gray-300 hover:text-white">
                                    <x-heroicon-m-bars-3 class="w-5 h-5" />
                                </button>
                                <template x-teleport="body">
                                    <div
                                        x-show="cardMenuOpen"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        @mouseenter="overPopup = true"
                                        @mouseleave="overPopup = false; setTimeout(() => { if (!overCard && !overPopup) cardMenuOpen = false }, 50)"
                                        class="fixed w-36 rounded-md shadow-lg z-[9999]"
                                        :style="'top: ' + menuPosition.top + 'px; left: ' + menuPosition.left + 'px; transform: translateY(-100%); background-color: #2d2d2d; border: 1px solid #000000;'">
                                        <div class="py-1">
                                            <button
                                                type="button"
                                                @click.stop.prevent="
                                                    cardMenuOpen = false;
                                                    const link = document.createElement('a');
                                                    link.href = '{{ $item->url }}';
                                                    link.download = '{{ $item->file_name }}';
                                                    link.style.display = 'none';
                                                    document.body.appendChild(link);
                                                    link.click();
                                                    document.body.removeChild(link);
                                                "
                                                class="flex items-center gap-2 w-full px-3 py-2 text-sm focus:outline-none text-left"
                                                style="color: #e5e7eb;"
                                                onmouseover="this.style.backgroundColor='#404040'; this.style.color='#ffffff';"
                                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#e5e7eb';">
                                                <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                                Download
                                            </button>
                                            <button
                                                type="button"
                                                @click.prevent="cardMenuOpen = false; $dispatch('open-delete-modal', {
                                                    id: {{ $item->id }}, 
                                                    name: {{ Js::from($item->name) }}
                                                })"
                                                class="flex items-center gap-2 w-full px-3 py-2 text-sm focus:outline-none"
                                                style="color: #f87171;"
                                                onmouseover="this.style.backgroundColor='#404040'; this.style.color='#fca5a5';"
                                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#f87171';">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
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
                            hasThumbnail: {{ $item->thumbnail_path ? 'true' : 'false' }},
                            videoSrc: '{{ $item->url }}'
                        }"
                        @mouseenter="
                            isHovering = true;
                            if ($refs.videoElement.src !== videoSrc) {
                                $refs.videoElement.src = videoSrc;
                                $refs.videoElement.load();
                            }
                            $refs.videoElement.currentTime = 0;
                            $refs.videoElement.play().catch(e => console.log('Could not play video:', e))
                        "
                        @mouseleave="
                            isHovering = false; 
                            $refs.videoElement.pause();
                            $refs.videoElement.currentTime = 0;
                        "
                        @click="$dispatch('open-media-preview', {
                            url: '{{ $item->url }}',
                            name: {{ Js::from($item->name) }},
                            type: '{{ $item->mime_type }}'
                        })">

                        <!-- Static thumbnail -->
                        <img
                            src="{{ $item->thumbnail_url ?: $item->url }}"
                            alt="{{ $item->name }}"
                            class="w-full h-full object-cover"
                            x-show="!isHovering"
                            loading="lazy">

                        <!-- Video preview on hover -->
                        <video
                            x-ref="videoElement"
                            class="w-full h-full object-cover"
                            x-show="isHovering"
                            muted
                            loop
                            preload="none">
                        </video>

                        <!-- Play button overlay -->
                        <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 transition-opacity duration-300"
                            x-show="!isHovering">
                            <svg class="w-6 h-6 text-white opacity-70" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>

                    @elseif(Str::contains($item->mime_type, 'image'))
                    <!-- Image thumbnail with GIF hover animation -->
                    <div class="absolute inset-0"
                        x-data="{ 
                            isHovering: false,
                            isGif: {{ pathinfo($item->file_name, PATHINFO_EXTENSION) === 'gif' ? 'true' : 'false' }},
                            hasThumbnail: {{ $item->thumbnail_path ? 'true' : 'false' }}
                        }"
                        @mouseenter="isHovering = true"
                        @mouseleave="isHovering = false"
                        @click="$dispatch('open-media-preview', {
                            url: '{{ $item->url }}',
                            name: {{ Js::from($item->name) }},
                            type: '{{ $item->mime_type }}'
                        })">

                        @if(pathinfo($item->file_name, PATHINFO_EXTENSION) === 'gif')
                        <!-- Static thumbnail for GIF -->
                        <img
                            src="{{ $item->thumbnail_url ?: $item->url }}"
                            alt="{{ $item->name }}"
                            class="w-full h-full object-cover"
                            x-show="!isHovering"
                            loading="lazy">

                        <!-- Animated GIF on hover -->
                        <img
                            src="{{ $item->url }}"
                            alt="{{ $item->file_name }}"
                            class="w-full h-full object-cover"
                            x-show="isHovering"
                            loading="lazy">

                        <!-- Play button overlay for GIF (matches video style) -->
                        <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 transition-opacity duration-300"
                            x-show="!isHovering">
                            <svg class="w-6 h-6 text-white opacity-70" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        @else
                        <!-- Regular static image -->
                        <img
                            src="{{ $item->url }}"
                            alt="{{ $item->file_name }}"
                            class="w-full h-full object-cover"
                            loading="lazy">
                        @endif
                    </div>

                    @elseif(Str::contains($item->mime_type, 'audio'))
                    <!-- Audio thumbnail -->
                    <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600"
                        @click="$dispatch('open-media-preview', {
                            url: '{{ $item->url }}',
                            name: {{ Js::from($item->name) }},
                            type: '{{ $item->mime_type }}'
                        })">
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
                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-red-500 to-red-700"
                        @click="$dispatch('open-media-preview', {
                            url: '{{ $item->url }}',
                            name: {{ Js::from($item->name) }},
                            type: '{{ $item->mime_type }}'
                        })">
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
    <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Showing {{ $media->firstItem() ?? 0 }} to {{ $media->lastItem() ?? 0 }} of {{ $media->total() }} results
        </div>
        <div>
            {{ $media->appends(['sort' => $sort, 'per_page' => $perPage, 'page' => $media->currentPage()])->onEachSide(1)->links('vendor.pagination.tailwind-no-info') }}
        </div>
    </div>
</div>

<!-- Modal Components -->
<x-modals.media-preview />
<x-modals.media-info />
<x-modals.delete-confirmation />

<script>
    // Prevent auto-scroll on middle mouse button down
    document.addEventListener('mousedown', function(e) {
        if (e.button === 1) { // Middle click
            const mediaItem = e.target.closest('[data-media-url]');
            if (mediaItem) {
                e.preventDefault();
            }
        }
    }, true);

    // Open media viewer on middle mouse button up (attempts background tab)
    document.addEventListener('mouseup', function(e) {
        if (e.button === 1) { // Middle click
            const mediaItem = e.target.closest('[data-media-url]');
            if (mediaItem) {
                e.preventDefault();
                e.stopPropagation();

                // Create a temporary link and simulate middle-click on it
                // This leverages the browser's native middle-click behavior which opens in background
                const link = document.createElement('a');
                link.href = mediaItem.dataset.mediaUrl;
                link.target = '_blank';
                link.rel = 'noopener';
                link.style.display = 'none';
                document.body.appendChild(link);

                // Dispatch a middle-click event on the link
                const clickEvent = new MouseEvent('click', {
                    button: 1,
                    ctrlKey: true, // Ctrl+click typically opens in background
                    bubbles: true,
                    cancelable: true,
                    view: window
                });
                link.dispatchEvent(clickEvent);

                // Fallback: also try regular click with ctrl
                link.click();

                document.body.removeChild(link);
            }
        }
    }, true);
</script>
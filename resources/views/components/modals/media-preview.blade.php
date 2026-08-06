<div
    x-data="{ 
        open: false,
        mediaUrl: '',
        mediaName: '',
        mediaType: '',
        isVideo: false,
        isAudio: false,
        isImage: false,
        isPdf: false,
        isOther: false,
        isFullscreen: false,
        stopMedia() {
            // Detach the source so the player releases the connection. A stalled
            // range request left holding a connection after the modal closes is
            // what made the whole app unresponsive until a hard refresh. Vidstack
            // aborts its in-flight request and tears down as soon as src is
            // cleared; the native <audio> needs an explicit load() to do the same.
            if (this.$refs.videoPlayer) this.$refs.videoPlayer.pause?.();
            if (this.$refs.audioPlayer) this.$refs.audioPlayer.pause();
            this.mediaUrl = '';
            this.$nextTick(() => {
                if (this.$refs.audioPlayer) this.$refs.audioPlayer.load();
            });
        },
        loadAndPlayVideo() {
            // Vidstack auto-plays via the `autoplay` attribute once it's ready;
            // this is a best-effort nudge for the user gesture that opened the
            // modal. Errors (autoplay policy, not-yet-ready) are non-fatal.
            this.$nextTick(() => {
                const player = this.$refs.videoPlayer;
                if (player && this.isVideo && typeof player.play === 'function') {
                    player.play().catch(() => {});
                }
            });
        },
        setupFullscreenListeners() {
            this.$nextTick(() => {
                if (this.$refs.videoPlayer) {
                    // Listen for fullscreen changes
                    document.addEventListener('fullscreenchange', () => {
                        this.isFullscreen = !!document.fullscreenElement;
                    });
                    document.addEventListener('webkitfullscreenchange', () => {
                        this.isFullscreen = !!document.webkitFullscreenElement;
                    });
                    document.addEventListener('mozfullscreenchange', () => {
                        this.isFullscreen = !!document.mozFullScreenElement;
                    });
                    document.addEventListener('MSFullscreenChange', () => {
                        this.isFullscreen = !!document.msFullscreenElement;
                    });
                }
            });
        }
    }"
    x-on:open-media-preview.window="
        open = true;
        mediaUrl = $event.detail.url;
        mediaName = $event.detail.name;
        mediaType = $event.detail.type;
        isVideo = mediaType.includes('video');
        isAudio = mediaType.includes('audio');
        isImage = mediaType.includes('image');
        isPdf = mediaType.includes('pdf');
        isOther = !isVideo && !isAudio && !isImage && !isPdf;
        if (isVideo) {
            loadAndPlayVideo();
            setupFullscreenListeners();
        }
    "
    x-show="open"
    x-transition.opacity
    x-transition:enter.duration.300ms
    x-transition:leave.duration.200ms
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 p-4"
    x-cloak
    {{-- Dismiss on a genuine backdrop click only (@click.self = target is the overlay
         itself). NOT @click.away on the content box: Vidstack portals its settings/speed
         menu to <body> (outside this subtree), so @click.away treated menu clicks as
         "outside" and closed the modal — clicking a playback-speed option just dismissed
         the player. --}}
    @click.self="if (open && !isFullscreen) { stopMedia(); open = false; }"
    @keydown.escape.window="if (open && !isFullscreen) { stopMedia(); open = false; }">
    <div class="relative w-full max-w-3xl bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden flex flex-col border border-white dark:border-gray-700">
        <!-- Modal header -->
        <div class="flex justify-between items-center p-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-medium text-gray-900 dark:text-white truncate" x-text="mediaName"></h3>
            <button
                @click="if (!isFullscreen) { stopMedia(); open = false; }"
                type="button"
                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                :class="{ 'pointer-events-none opacity-50': isFullscreen }">
                <x-heroicon-m-x-mark class="w-5 h-5" />
            </button>
        </div>

        <!-- Modal content section -->
        <div class="p-2 overflow-auto" style="max-height: calc(85vh - 110px);">
            <div class="flex items-center justify-center">
                <!-- Video content (Vidstack web component; wire:ignore so Livewire
                     doesn't morph the player's internal DOM out from under it). -->
                <template x-if="isVideo">
                    <div class="w-full flex items-center justify-center" wire:ignore>
                        {{-- Custom chrome: controls sit BELOW the video (never over
                             burned-in captions). Styles: resources/css/vidstack-custom.css --}}
                        <media-player
                            x-ref="videoPlayer"
                            :src="mediaUrl"
                            :title="mediaName"
                            view-type="video"
                            class="mgd-player w-full"
                            style="max-width: 100%;"
                            playsinline
                            autoplay
                            @fullscreen-change="isFullscreen = !!$event.detail">
                            <div class="mgd-vds-stage">
                                <media-provider></media-provider>
                                <media-gesture event="pointerup" action="toggle:paused"></media-gesture>
                                <div class="mgd-vds-buffering">
                                    <svg fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"></circle><path stroke="currentColor" stroke-linecap="round" stroke-width="3" d="M12 2a10 10 0 0 1 10 10"></path></svg>
                                </div>
                            </div>
                            <media-controls class="mgd-vds-bar">
                                <media-controls-group class="mgd-vds-row">
                                    <media-time-slider class="vds-time-slider vds-slider">
                                        <div class="vds-slider-track"></div>
                                        <div class="vds-slider-track-fill vds-slider-track"></div>
                                        <div class="vds-slider-progress vds-slider-track"></div>
                                        <div class="vds-slider-thumb"></div>
                                    </media-time-slider>
                                </media-controls-group>
                                <media-controls-group class="mgd-vds-row">
                                    <media-play-button class="vds-button" aria-label="Play">
                                        <svg class="mgd-icon mgd-i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72c0 .8.87 1.3 1.56.9l11-6.86a1.05 1.05 0 0 0 0-1.8l-11-6.86c-.69-.4-1.56.1-1.56.9Z"/></svg>
                                        <svg class="mgd-icon mgd-i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>
                                    </media-play-button>
                                    <media-mute-button class="vds-button" aria-label="Mute">
                                        <svg class="mgd-icon mgd-i-vol" viewBox="0 0 24 24" fill="currentColor"><path d="M4 9v6h4l5 4V5L8 9H4Zm12.5 3a3.5 3.5 0 0 0-1.8-3.06v6.11A3.5 3.5 0 0 0 16.5 12Zm-1.8-7.35v2.1a5.5 5.5 0 0 1 0 10.5v2.1a7.6 7.6 0 0 0 0-14.7Z"/></svg>
                                        <svg class="mgd-icon mgd-i-muted" viewBox="0 0 24 24" fill="currentColor"><path d="M4 9v6h4l5 4V5L8 9H4Zm14.6 3 2.2-2.2-1.4-1.4-2.2 2.2-2.2-2.2-1.4 1.4L15.8 12l-2.2 2.2 1.4 1.4 2.2-2.2 2.2 2.2 1.4-1.4L18.6 12Z"/></svg>
                                    </media-mute-button>
                                    <media-volume-slider class="vds-slider" aria-label="Volume">
                                        <div class="vds-slider-track"></div>
                                        <div class="vds-slider-track-fill vds-slider-track"></div>
                                        <div class="vds-slider-thumb"></div>
                                    </media-volume-slider>
                                    <div class="mgd-vds-time">
                                        <media-time type="current"></media-time>
                                        <span>/</span>
                                        <media-time type="duration"></media-time>
                                    </div>
                                    <div class="mgd-vds-spacer"></div>
                                    <media-menu>
                                        <media-menu-button class="vds-button" aria-label="Playback speed">
                                            <svg class="mgd-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2.05v2.02A8 8 0 0 1 13 20v2.02A10 10 0 0 0 13 2.05ZM11 2.05A10 10 0 0 0 4.7 5.1L6.12 6.5A8 8 0 0 1 11 4.07V2.05ZM4.07 11H2.05a9.95 9.95 0 0 0 0 2h2.02a8.06 8.06 0 0 1 0-2ZM6.12 17.5 4.7 18.9A10 10 0 0 0 11 21.95v-2.02a8 8 0 0 1-4.88-2.43ZM12 7l-1 5.5 4 2.5 1-1.5-2.7-1.7L14 7.3 12 7Z"/></svg>
                                        </media-menu-button>
                                        <media-menu-items class="vds-menu-items" placement="top end">
                                            <media-speed-radio-group normal-label="Normal">
                                                <template>
                                                    <media-radio class="vds-radio">
                                                        <div class="vds-radio-check"></div>
                                                        <span data-part="label"></span>
                                                    </media-radio>
                                                </template>
                                            </media-speed-radio-group>
                                        </media-menu-items>
                                    </media-menu>
                                    <media-pip-button class="vds-button" aria-label="Picture in picture">
                                        <svg class="mgd-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 4H3a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1Zm-1 14H4V6h16v12Zm-2-7h-6v5h6v-5Z"/></svg>
                                    </media-pip-button>
                                    <media-fullscreen-button class="vds-button" aria-label="Fullscreen">
                                        <svg class="mgd-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h6v2H6v4H4V4Zm10 0h6v6h-2V6h-4V4ZM4 14h2v4h4v2H4v-6Zm14 0h2v6h-6v-2h4v-4Z"/></svg>
                                    </media-fullscreen-button>
                                </media-controls-group>
                            </media-controls>
                        </media-player>
                    </div>
                </template>

                <!-- Audio content -->
                <template x-if="isAudio">
                    <div class="w-full flex flex-col items-center justify-center p-4">
                        <div class="w-full max-w-md bg-gray-100 dark:bg-gray-700 rounded-lg p-6 shadow-inner">
                            <div class="flex justify-center mb-6">
                                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-center text-gray-800 dark:text-gray-200 mb-4 font-medium" x-text="mediaName"></p>
                            <audio x-ref="audioPlayer" class="w-full" controls>
                                <source :src="mediaUrl" :type="mediaType">
                                Your browser does not support audio playback.
                            </audio>
                        </div>
                    </div>
                </template>

                <!-- Image content -->
                <template x-if="isImage">
                    <img :src="mediaUrl" :alt="mediaName" style="max-height: calc(80vh - 110px); max-width: 100%;">
                </template>

                <!-- PDF Document -->
                <template x-if="isPdf">
                    <div class="w-full h-full flex flex-col items-center">
                        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 mb-4 text-center">
                            <p class="text-gray-800 dark:text-gray-200">PDF Document Preview</p>
                        </div>
                        <iframe :src="mediaUrl" class="w-full" style="height: calc(70vh - 110px);"></iframe>
                    </div>
                </template>

                <!-- Other file types -->
                <template x-if="isOther">
                    <div class="w-full flex flex-col items-center justify-center p-8 text-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2" x-text="mediaName"></h3>
                        <p class="mb-4 text-gray-500 dark:text-gray-400">This file type cannot be previewed directly.</p>
                        <div class="flex justify-center space-x-3">
                            <a :href="mediaUrl" download class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download File
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
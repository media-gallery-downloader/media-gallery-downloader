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
            if (this.$refs.videoPlayer) {
                this.$refs.videoPlayer.pause();
                this.$refs.videoPlayer.currentTime = 0;
            }
            if (this.$refs.audioPlayer) {
                this.$refs.audioPlayer.pause();
                this.$refs.audioPlayer.currentTime = 0;
            }
        },
        playVideo() {
            this.$nextTick(() => {
                if (this.$refs.videoPlayer && this.isVideo) {
                    this.$refs.videoPlayer.play().catch(e => {
                        console.log('Could not auto-play video:', e);
                    });
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
            playVideo();
            setupFullscreenListeners();
        }
    "
    x-show="open"
    x-transition.opacity
    x-transition:enter.duration.300ms
    x-transition:leave.duration.200ms
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75 p-4"
    x-cloak
    @keydown.escape.window="if (open && !isFullscreen) { stopMedia(); open = false; }">
    <div @click.away="if (open && !isFullscreen) { stopMedia(); open = false; }" class="relative w-full max-w-3xl bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden flex flex-col border border-white dark:border-gray-700">
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
                <!-- Video content -->
                <template x-if="isVideo">
                    <div class="w-full flex items-center justify-center">
                        <video
                            x-ref="videoPlayer"
                            style="max-height: calc(80vh - 110px); max-width: 100%;"
                            controls
                            @loadeddata="playVideo()"
                            @fullscreenchange="isFullscreen = !!document.fullscreenElement">
                            <source :src="mediaUrl" :type="mediaType">
                            Your browser does not support video playback.
                        </video>
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
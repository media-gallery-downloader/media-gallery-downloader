<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Video Quality Preferences
    |--------------------------------------------------------------------------
    |
    | Define the preferred video quality and format for downloads.
    | These settings affect yt-dlp downloads.
    |
    */

    'video_quality' => [
        // Maximum resolution preference
        'max_height' => 1080,

        // Preferred format
        'format' => 'mp4',

        // Quality fallback chain
        'format_selector' => 'bestvideo[height<=1080]+bestaudio/best[height<=1080]/bestvideo[height<=720]+bestaudio/best[height<=720]/best',

        // Additional options
        'embed_subs' => true,
        'auto_subs' => true,
        'sub_lang' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication (cookies)
    |--------------------------------------------------------------------------
    |
    | These cookies are passed to yt-dlp for EVERY site, not just YouTube. Use
    | them for age-restricted YouTube videos and for sites that now require a
    | login to fetch metadata (e.g. Reddit, which gates posts behind an account).
    | A single Netscape cookies.txt can hold cookies for multiple domains.
    | Options:
    |   - 'cookies_file': Path to a cookies.txt file (Netscape format)
    |   - 'cookies_from_browser': Browser name (chrome, firefox, edge, safari, opera, brave)
    |
    | To export cookies manually:
    |   1. Install a browser extension like "Get cookies.txt LOCALLY"
    |   2. Log into the site(s) you download from (youtube.com, reddit.com, ...)
    |   3. Export their cookies to storage/app/cookies.txt
    |
    | Or use browser extraction (requires browser to be closed):
    |   Set YTDLP_COOKIES_FROM_BROWSER=chrome in your .env
    |
    */

    'youtube' => [
        'cookies_file' => env('YTDLP_COOKIES_FILE', storage_path('app/cookies.txt')),
        'cookies_from_browser' => env('YTDLP_COOKIES_FROM_BROWSER', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Download Timeouts
    |--------------------------------------------------------------------------
    */

    'transcode' => [
        // VAAPI render node for hardware-accelerated re-encoding (media:reencode
        // --accel=vaapi). Requires /dev/dri passed into the container.
        'vaapi_device' => env('MGD_VAAPI_DEVICE', '/dev/dri/renderD128'),
    ],

    'timeouts' => [
        'download' => 600,  // 10 minutes for video downloads
        'metadata' => 120,  // 2 minutes for metadata fetching
        'reencode' => 21600, // 6 hours - re-encoding a large library file is slow
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk' => 'public',
        'media_path' => 'media',
        'thumbnail_path' => 'thumbnails',
    ],

    /*
    |--------------------------------------------------------------------------
    | Download Limits & Security
    |--------------------------------------------------------------------------
    |
    | block_private_hosts: refuse direct (HTTP) downloads from URLs that resolve
    |   to private/loopback/link-local/reserved IPs (SSRF protection - e.g. the
    |   cloud metadata endpoint or internal services on the Docker network).
    |   Disable only if you intentionally download from your own LAN.
    | max_download_bytes: hard cap on a single direct download (0 = unlimited).
    | max_archive_bytes: hard cap on total uncompressed size when extracting an
    |   uploaded archive (0 = unlimited) - guards against zip bombs.
    |
    */

    'downloads' => [
        'block_private_hosts' => env('MGD_BLOCK_PRIVATE_HOSTS', true),
        'max_download_bytes' => (int) env('MGD_MAX_DOWNLOAD_BYTES', 0),
        'max_archive_bytes' => (int) env('MGD_MAX_ARCHIVE_BYTES', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Codec Baseline (browser compatibility)
    |--------------------------------------------------------------------------
    |
    | `media:probe-codecs` flags any file whose video/audio codec is NOT in
    | these lists as potentially-incompatible. Tune them to your clients:
    | HEVC (h265) is included by default because modern setups (e.g. Firefox on
    | Windows with the HEVC extension / hardware decode) play it and it's smaller.
    | Remove `hevc` here if you also serve to devices/browsers that can't decode
    | it and want those flagged for re-encoding.
    |
    */

    'codecs' => [
        'baseline_video' => array_filter(array_map('trim', explode(',', (string) env('MGD_BASELINE_VIDEO', 'h264,hevc,vp8,vp9,av1')))),
        'baseline_audio' => array_filter(array_map('trim', explode(',', (string) env('MGD_BASELINE_AUDIO', 'aac,mp3,opus,vorbis,flac')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk Import Settings
    |--------------------------------------------------------------------------
    |
    | Configure paths for bulk importing video files from a directory.
    | Place video files in the 'incoming' directory, and they will be
    | automatically imported by the scheduled job. Failed imports are
    | moved to the 'failed' directory with an accompanying .log file.
    |
    */

    'import' => [
        'incoming_path' => env('MGD_IMPORT_INCOMING_PATH', storage_path('app/data/import/incoming')),
        'failed_path' => env('MGD_IMPORT_FAILED_PATH', storage_path('app/data/import/failed')),
        'batch_size' => env('MGD_IMPORT_BATCH_SIZE', 10),  // Number of files to queue at once
    ],
];

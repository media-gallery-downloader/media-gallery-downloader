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
    | YouTube Authentication
    |--------------------------------------------------------------------------
    |
    | For age-restricted videos, you need to provide authentication.
    | Options:
    |   - 'cookies_file': Path to a cookies.txt file (Netscape format)
    |   - 'cookies_from_browser': Browser name (chrome, firefox, edge, safari, opera, brave)
    |
    | To export cookies manually:
    |   1. Install a browser extension like "Get cookies.txt LOCALLY"
    |   2. Log into YouTube
    |   3. Export cookies for youtube.com to storage/app/cookies.txt
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

    'timeouts' => [
        'download' => 600,  // 10 minutes for video downloads
        'metadata' => 30,   // 30 seconds for metadata fetching
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

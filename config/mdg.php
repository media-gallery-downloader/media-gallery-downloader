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
];

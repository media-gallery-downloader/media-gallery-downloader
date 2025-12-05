<?php

namespace App\Helpers;

use Symfony\Component\Mime\MimeTypes;

class MimeTypeHelper
{
    /**
     * Get file extension from MIME type
     *
     * @param  string  $mimeType
     * @return string
     */
    public static function getExtensionFromMimeType($mimeType)
    {
        $mimeTypes = MimeTypes::getDefault();
        $extensions = $mimeTypes->getExtensions($mimeType);

        return $extensions[0] ?? '';
    }

    /**
     * Get MIME type from file extension
     *
     * @param  string  $extension
     * @return string
     */
    public static function getMimeTypeFromExtension($extension)
    {
        $mimeTypes = MimeTypes::getDefault();
        $mimes = $mimeTypes->getMimeTypes($extension);

        return $mimes[0] ?? '';
    }

    /**
     * Check if the MIME type corresponds to a media file (image, video, audio)
     *
     * @param  string  $mimeType
     * @return bool
     */
    public static function isMedia($mimeType)
    {
        if (empty($mimeType)) {
            return false;
        }

        return str_starts_with($mimeType, 'image/') ||
            str_starts_with($mimeType, 'video/') ||
            str_starts_with($mimeType, 'audio/');
    }
}

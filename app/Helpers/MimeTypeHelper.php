<?php

namespace App\Helpers;

use Symfony\Component\Mime\MimeTypes;

class MimeTypeHelper
{
    /**
     * Get file extension from MIME type
     * 
     * @param string $mimeType
     * @return string
     */
    public static function getExtensionFromMimeType($mimeType)
    {
        $mimeTypes = MimeTypes::getDefault();
        $extensions = $mimeTypes->getExtensions($mimeType);

        return $extensions[0] ?? '';
    }
}

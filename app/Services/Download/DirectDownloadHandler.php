<?php

namespace App\Services\Download;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use Illuminate\Support\Facades\Http;

/**
 * Downloads media directly via HTTP for direct file URLs
 */
class DirectDownloadHandler extends BaseDownloadHandler
{
    protected int $timeout = 300;

    public function getName(): string
    {
        return 'direct';
    }

    public function canHandle(string $url): bool
    {
        // Can handle any valid URL
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function download(string $url, string $downloadId, ?callable $progressCallback = null): Media
    {
        $tempDir = $this->createTempDirectory('direct');

        try {
            // Validate URL
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid URL provided');
            }

            // SSRF guard: reject private/reserved targets before any network call.
            $this->assertPublicUrl($url);

            // Determine filename
            $originalFilename = $this->determineFilename($url);

            // Download the file
            $outputFilePath = $tempDir.'/'.$originalFilename;
            $this->downloadFile($url, $outputFilePath);

            // Get display name and determine mime type
            $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);
            $mimeType = $this->determineMimeType($outputFilePath, $originalFilename);

            // Create media record
            $media = $this->storeAndCreateMedia($outputFilePath, $displayName, $url, $mimeType);

            $this->cleanupTempDirectory($tempDir);

            return $media;
        } catch (\Exception $e) {
            $this->cleanupTempDirectory($tempDir);
            throw $e;
        }
    }

    /**
     * Determine the filename from URL or headers
     */
    protected function determineFilename(string $url): string
    {
        $originalFilename = basename(parse_url($url, PHP_URL_PATH));
        $headers = null;

        if (empty($originalFilename)) {
            $headers = get_headers($url, true);
            if ($headers !== false && isset($headers['Content-Disposition'])) {
                if (preg_match('/filename="(.+?)"/', $headers['Content-Disposition'], $matches)) {
                    $originalFilename = $matches[1];
                }
            }
        }

        // If still empty, use a hash of the URL
        if (empty($originalFilename)) {
            $originalFilename = md5($url);

            // Try to determine extension from content type
            $contentType = is_array($headers) ? ($headers['Content-Type'] ?? '') : '';
            if (is_array($contentType)) {
                $contentType = $contentType[0] ?? '';
            }
            $extension = MimeTypeHelper::getExtensionFromMimeType($contentType);
            if (! empty($extension)) {
                $originalFilename .= '.'.$extension;
            }
        }

        return $originalFilename;
    }

    /**
     * Download the file using HTTP client
     */
    protected function downloadFile(string $url, string $outputPath): void
    {
        $maxBytes = (int) config('mgd.downloads.max_download_bytes', 0);

        $response = Http::withOptions([
            'sink' => $outputPath,
            'timeout' => $this->timeout,
            // Re-validate every redirect hop so a public URL can't bounce us to
            // an internal address (SSRF via redirect).
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => false,
                'protocols' => ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri) {
                    $this->assertPublicUrl((string) $uri);
                },
            ],
            // Abort early if the server advertises a body larger than the cap.
            'on_headers' => function ($response) use ($maxBytes) {
                if ($maxBytes > 0) {
                    $length = (int) ($response->getHeaderLine('Content-Length'));
                    if ($length > $maxBytes) {
                        throw new \Exception('File exceeds the maximum allowed download size.');
                    }
                }
            },
        ])->get($url);

        if ($response->failed()) {
            throw new \Exception('Error downloading file. Status: '.$response->status());
        }

        // Servers that omit Content-Length can still overshoot the cap.
        if ($maxBytes > 0 && filesize($outputPath) > $maxBytes) {
            @unlink($outputPath);
            throw new \Exception('File exceeds the maximum allowed download size.');
        }
    }

    /**
     * Guard against SSRF: refuse to fetch URLs that resolve to private,
     * loopback, link-local or otherwise reserved IP ranges (e.g. cloud metadata
     * endpoints like 169.254.169.254, or internal services on the Docker
     * network).
     *
     * Note: this resolves DNS at check time; it does not fully protect against
     * DNS-rebinding (a TOCTOU between this check and the connection). For this
     * app's threat model (admin-entered URLs on a private network) that residual
     * risk is acceptable.
     */
    protected function assertPublicUrl(string $url): void
    {
        if (! config('mgd.downloads.block_private_hosts', true)) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            throw new \Exception('Refusing to download: URL has no host.');
        }

        $ips = $this->resolveHostIps($host);
        if (empty($ips)) {
            throw new \Exception("Refusing to download: could not resolve host '{$host}'.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \Exception("Refusing to download from a private or reserved address ({$ip}).");
            }
        }
    }

    /**
     * Resolve a host to all of its candidate IP addresses (IPv4 + IPv6). If the
     * host is already an IP literal it is returned as-is.
     *
     * @return string[]
     */
    protected function resolveHostIps(string $host): array
    {
        $host = trim($host, '[]'); // strip brackets from IPv6 literals like [::1]

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Determine the mime type of the downloaded file
     */
    protected function determineMimeType(string $filePath, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Get extension from file if not in filename
        if (empty($extension)) {
            $mimeType = mime_content_type($filePath);
            $extension = MimeTypeHelper::getExtensionFromMimeType($mimeType);
            if (empty($extension)) {
                $extension = 'bin';
            }
        }

        $mimeType = mime_content_type($filePath);

        // If mime type is generic, try to guess from extension
        if ($mimeType === 'application/octet-stream' || empty($mimeType)) {
            $extMime = MimeTypeHelper::getMimeTypeFromExtension($extension);
            if (! empty($extMime)) {
                $mimeType = $extMime;
            }
        }

        return $mimeType;
    }

    /**
     * Set the timeout for downloads
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }
}

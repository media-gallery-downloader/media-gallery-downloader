<?php

namespace App\Services\Upload;

/**
 * Handles extraction of various archive formats
 */
class ArchiveExtractor
{
    protected string $extractDir;

    /** Max total uncompressed size in bytes (0 = unlimited). Zip-bomb guard. */
    protected int $maxBytes = 0;

    /**
     * Extract an archive to a directory
     *
     * @param  string  $filePath  Path to the archive file
     * @param  string  $extractDir  Directory to extract to
     *
     * @throws \Exception If extraction fails
     */
    public function extract(string $filePath, string $extractDir): void
    {
        $this->extractDir = $extractDir;
        $this->maxBytes = (int) config('mgd.downloads.max_archive_bytes', 0);
        $extension = $this->getExtension($filePath);

        match ($extension) {
            'zip' => $this->extractZip($filePath),
            'tar' => $this->extractTar($filePath),
            'tar.gz', 'tgz' => $this->extractTarGz($filePath),
            'tar.bz2', 'tbz2' => $this->extractTarBz2($filePath),
            '7z' => $this->extract7z($filePath),
            'rar' => $this->extractRar($filePath),
            default => throw new \Exception("Unsupported archive format: {$extension}"),
        };
    }

    /**
     * Get the archive extension, handling double extensions
     */
    protected function getExtension(string $filePath): string
    {
        $filename = basename($filePath);

        // Handle double extensions like .tar.gz
        if (preg_match('/\.(tar\.(gz|bz2))$/i', $filename, $matches)) {
            return strtolower($matches[1]);
        }

        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Extract ZIP archive
     */
    protected function extractZip(string $filePath): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \Exception('Failed to open ZIP archive');
        }

        // Zip-bomb guard: the central directory reports uncompressed sizes, so we
        // can reject before writing anything to disk.
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat !== false) {
                $total += (int) $stat['size'];
            }
        }
        $this->assertWithinLimit($total);

        $zip->extractTo($this->extractDir);
        $zip->close();
    }

    /**
     * Extract TAR archive
     */
    protected function extractTar(string $filePath): void
    {
        $phar = new \PharData($filePath);
        $this->assertPharWithinLimit($phar);
        $phar->extractTo($this->extractDir);
    }

    /**
     * Extract TAR.GZ archive
     */
    protected function extractTarGz(string $filePath): void
    {
        $phar = new \PharData($filePath);
        $phar->decompress();

        $tarPath = $this->getDecompressedTarPath($filePath, ['gz', 'tgz']);

        if (file_exists($tarPath)) {
            $tar = new \PharData($tarPath);
            $this->assertPharWithinLimit($tar);
            $tar->extractTo($this->extractDir);
            @unlink($tarPath);
        }
    }

    /**
     * Extract TAR.BZ2 archive
     */
    protected function extractTarBz2(string $filePath): void
    {
        $phar = new \PharData($filePath);
        $phar->decompress();

        $tarPath = $this->getDecompressedTarPath($filePath, ['bz2', 'tbz2']);

        if (file_exists($tarPath)) {
            $tar = new \PharData($tarPath);
            $this->assertPharWithinLimit($tar);
            $tar->extractTo($this->extractDir);
            @unlink($tarPath);
        }
    }

    /**
     * Get the path to the decompressed tar file
     */
    protected function getDecompressedTarPath(string $filePath, array $extensions): string
    {
        foreach ($extensions as $ext) {
            if (preg_match('/\.'.$ext.'$/i', $filePath)) {
                return preg_replace('/\.'.$ext.'$/i', $ext === 'tgz' || $ext === 'tbz2' ? '.tar' : '', $filePath);
            }
        }

        return $filePath;
    }

    /**
     * Extract 7z archive (requires p7zip)
     */
    protected function extract7z(string $filePath): void
    {
        $command = sprintf(
            '7z x %s -o%s -y 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($this->extractDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract 7z archive: '.implode("\n", $output));
        }

        // External extractors don't expose sizes cheaply up front, so enforce
        // the limit on the extracted result (the caller cleans up on failure).
        $this->assertWithinLimit($this->directorySize($this->extractDir));
    }

    /**
     * Extract RAR archive (requires unar / The Unarchiver)
     */
    protected function extractRar(string $filePath): void
    {
        // The image installs `unar` (The Unarchiver), not `unrar`. unar handles
        // RAR (and other formats); -no-directory extracts the contents directly
        // into the target without an enclosing folder.
        $command = sprintf(
            'unar -quiet -no-directory -force-overwrite -output-directory %s %s 2>&1',
            escapeshellarg($this->extractDir),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract RAR archive: '.implode("\n", $output));
        }

        $this->assertWithinLimit($this->directorySize($this->extractDir));
    }

    /**
     * Throw if the given byte count exceeds the configured archive size limit.
     */
    protected function assertWithinLimit(int $bytes): void
    {
        if ($this->maxBytes > 0 && $bytes > $this->maxBytes) {
            throw new \Exception('Archive exceeds the maximum allowed uncompressed size.');
        }
    }

    /**
     * Sum the uncompressed sizes reported by a PharData archive, aborting as soon
     * as the limit is exceeded.
     */
    protected function assertPharWithinLimit(\PharData $phar): void
    {
        if ($this->maxBytes <= 0) {
            return;
        }

        $total = 0;
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            $total += $file->getSize();
            $this->assertWithinLimit($total);
        }
    }

    /**
     * Total size in bytes of all files within a directory tree.
     */
    protected function directorySize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Check if a file is a supported archive
     */
    public static function isSupported(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Handle double extensions
        if (preg_match('/\.(tar\.(gz|bz2))$/i', $filename)) {
            return true;
        }

        return in_array($extension, ['zip', 'tar', 'tgz', 'tbz2', '7z', 'rar']);
    }
}

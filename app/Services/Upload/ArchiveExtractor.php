<?php

namespace App\Services\Upload;

/**
 * Handles extraction of various archive formats
 */
class ArchiveExtractor
{
    protected string $extractDir;

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
        $zip->extractTo($this->extractDir);
        $zip->close();
    }

    /**
     * Extract TAR archive
     */
    protected function extractTar(string $filePath): void
    {
        $phar = new \PharData($filePath);
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
    }

    /**
     * Extract RAR archive (requires unrar)
     */
    protected function extractRar(string $filePath): void
    {
        $command = sprintf(
            'unrar x -o+ %s %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($this->extractDir.'/')
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract RAR archive: '.implode("\n", $output));
        }
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

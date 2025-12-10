<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class UpdaterService
{
    /**
     * Check for updates to yt-dlp and update if needed
     *
     * @return bool True if updated or already up-to-date, false if error
     */
    public function checkAndUpdateYtdlp(): bool
    {
        try {
            Log::info('Checking for yt-dlp updates');

            // Get the current version
            $versionProcess = new Process(['yt-dlp', '--version']);
            $versionProcess->run();

            if (! $versionProcess->isSuccessful()) {
                Log::warning('yt-dlp not found or not working properly. Attempting to install it.');

                return $this->installYtdlp();
            }

            $currentVersion = trim($versionProcess->getOutput());

            // Get the latest version from GitHub API
            $response = Http::get('https://api.github.com/repos/yt-dlp/yt-dlp/releases/latest');
            if (! $response->successful()) {
                Log::warning('Failed to check for yt-dlp updates: HTTP '.$response->status());

                return false;
            }

            $latestRelease = $response->json();
            $latestVersion = ltrim($latestRelease['tag_name'] ?? '', 'v');

            if (empty($latestVersion)) {
                Log::warning('Failed to parse latest version from GitHub response');

                return false;
            }

            // Compare versions
            if (version_compare($currentVersion, $latestVersion, '>=')) {
                Log::info("yt-dlp is up to date (version: {$currentVersion})");

                return true; // Already up to date
            }

            Log::info("Updating yt-dlp from version {$currentVersion} to {$latestVersion}");

            return $this->downloadAndInstallYtdlp();
        } catch (\Exception $e) {
            Log::error('Error updating yt-dlp: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Download and install yt-dlp
     *
     * @return bool True if installed successfully, false if error
     */
    private function installYtdlp(): bool
    {
        try {
            Log::info('Installing yt-dlp...');

            $installPath = '/usr/local/bin/yt-dlp';
            $tempFile = storage_path('app/temp/ytdlp_install_'.uniqid());

            // Ensure temp directory exists
            if (! is_dir(dirname($tempFile))) {
                mkdir(dirname($tempFile), 0755, true);
            }

            // Download the latest version
            $downloadProcess = new Process([
                'curl',
                '-L',
                'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp',
                '-o',
                $tempFile,
            ]);

            $downloadProcess->setTimeout(60); // 1 minute timeout
            $downloadProcess->run();

            if (! $downloadProcess->isSuccessful()) {
                Log::warning('Failed to download yt-dlp: '.$downloadProcess->getErrorOutput());
                @unlink($tempFile);

                return false;
            }

            // Move to install location
            $moveProcess = new Process(['mv', $tempFile, $installPath]);
            $moveProcess->run();

            if (! $moveProcess->isSuccessful()) {
                Log::warning('Failed to install yt-dlp: '.$moveProcess->getErrorOutput());
                @unlink($tempFile);

                return false;
            }

            // Make executable
            $chmodProcess = new Process(['chmod', 'a+rx', $installPath]);
            $chmodProcess->run();

            if (! $chmodProcess->isSuccessful()) {
                Log::warning('Failed to make yt-dlp executable: '.$chmodProcess->getErrorOutput());
                // Continue anyway
            }

            // Verify installation
            $verifyProcess = new Process([$installPath, '--version']);
            $verifyProcess->run();

            if ($verifyProcess->isSuccessful()) {
                $version = trim($verifyProcess->getOutput());
                Log::info("Successfully installed yt-dlp version {$version}");

                return true;
            } else {
                Log::warning('yt-dlp was installed but verification failed: '.$verifyProcess->getErrorOutput());

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error installing yt-dlp: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Download and install the latest version of yt-dlp
     */
    private function downloadAndInstallYtdlp(): bool
    {
        // Find yt-dlp location
        $whichProcess = new Process(['which', 'yt-dlp']);
        $whichProcess->run();

        if (! $whichProcess->isSuccessful()) {
            Log::warning('Could not determine yt-dlp path');

            return $this->installYtdlp(); // Try fresh install
        }

        $ytdlpPath = trim($whichProcess->getOutput());
        $tempFile = storage_path('app/temp/ytdlp_update_'.uniqid());

        // Ensure temp directory exists
        if (! is_dir(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        // Download the new version to a temporary file
        $downloadProcess = new Process([
            'curl',
            '-L',
            'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp',
            '-o',
            $tempFile,
        ]);

        $downloadProcess->setTimeout(60); // 1 minute timeout
        $downloadProcess->run();

        if (! $downloadProcess->isSuccessful()) {
            Log::warning('Failed to download latest yt-dlp: '.$downloadProcess->getErrorOutput());
            @unlink($tempFile);

            return false;
        }

        // Move to yt-dlp location (app user should own this file)
        $updateProcess = new Process([
            'mv',
            $tempFile,
            $ytdlpPath,
        ]);
        $updateProcess->run();

        if (! $updateProcess->isSuccessful()) {
            Log::warning('Failed to update yt-dlp: '.$updateProcess->getErrorOutput());
            @unlink($tempFile);

            return false;
        }

        // Make executable
        $chmodProcess = new Process(['chmod', 'a+rx', $ytdlpPath]);
        $chmodProcess->run();

        if (! $chmodProcess->isSuccessful()) {
            Log::warning('Failed to make yt-dlp executable: '.$chmodProcess->getErrorOutput());
            // Continue anyway, it might still work
        }

        Log::info('Successfully updated yt-dlp');

        return true;
    }
}

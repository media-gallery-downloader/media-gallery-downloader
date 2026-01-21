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

            // Get the latest version from GitHub API (nightly builds)
            $response = Http::get('https://api.github.com/repos/yt-dlp/yt-dlp-nightly-builds/releases/latest');
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
     * Check for updates to Deno and update if needed
     *
     * @return bool True if updated or already up-to-date, false if error
     */
    public function checkAndUpdateDeno(): bool
    {
        try {
            Log::info('Checking for Deno updates');

            // Get the current version
            $versionProcess = new Process(['deno', '--version']);
            $versionProcess->run();

            if (! $versionProcess->isSuccessful()) {
                Log::warning('Deno not found or not working properly. Attempting to install it.');

                return $this->installDeno();
            }

            $output = $versionProcess->getOutput();
            $currentVersion = null;
            if (preg_match('/deno (\S+)/', $output, $matches)) {
                $currentVersion = $matches[1];
            }

            if (! $currentVersion) {
                Log::warning('Could not parse Deno version');

                return false;
            }

            // Get the latest version from GitHub API
            $response = Http::get('https://api.github.com/repos/denoland/deno/releases/latest');
            if (! $response->successful()) {
                Log::warning('Failed to check for Deno updates: HTTP '.$response->status());

                return false;
            }

            $latestRelease = $response->json();
            $latestVersion = ltrim($latestRelease['tag_name'] ?? '', 'v');

            if (empty($latestVersion)) {
                Log::warning('Failed to parse latest Deno version from GitHub response');

                return false;
            }

            // Compare versions
            if (version_compare($currentVersion, $latestVersion, '>=')) {
                Log::info("Deno is up to date (version: {$currentVersion})");

                return true; // Already up to date
            }

            Log::info("Updating Deno from version {$currentVersion} to {$latestVersion}");

            return $this->downloadAndInstallDeno();
        } catch (\Exception $e) {
            Log::error('Error updating Deno: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Install Deno
     *
     * @return bool True if installed successfully, false if error
     */
    private function installDeno(): bool
    {
        try {
            Log::info('Installing Deno...');

            return $this->downloadAndInstallDeno();
        } catch (\Exception $e) {
            Log::error('Error installing Deno: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Download and install the latest version of Deno
     */
    private function downloadAndInstallDeno(): bool
    {
        $denoPath = '/usr/local/bin/deno';
        $tempDir = storage_path('app/temp');
        $tempZip = $tempDir.'/deno_update_'.uniqid().'.zip';

        // Ensure temp directory exists
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Download the new version
        $downloadProcess = new Process([
            'curl',
            '-fsSL',
            'https://github.com/denoland/deno/releases/latest/download/deno-x86_64-unknown-linux-gnu.zip',
            '-o',
            $tempZip,
        ]);

        $downloadProcess->setTimeout(120); // 2 minute timeout
        $downloadProcess->run();

        if (! $downloadProcess->isSuccessful()) {
            Log::warning('Failed to download latest Deno: '.$downloadProcess->getErrorOutput());
            @unlink($tempZip);

            return false;
        }

        // Extract deno binary
        $extractProcess = new Process([
            'unzip',
            '-o',
            $tempZip,
            '-d',
            $tempDir,
        ]);
        $extractProcess->run();

        if (! $extractProcess->isSuccessful()) {
            Log::warning('Failed to extract Deno: '.$extractProcess->getErrorOutput());
            @unlink($tempZip);

            return false;
        }

        // Move to deno location
        $moveProcess = new Process([
            'mv',
            $tempDir.'/deno',
            $denoPath,
        ]);
        $moveProcess->run();

        if (! $moveProcess->isSuccessful()) {
            Log::warning('Failed to update Deno: '.$moveProcess->getErrorOutput());
            @unlink($tempZip);
            @unlink($tempDir.'/deno');

            return false;
        }

        // Make executable
        $chmodProcess = new Process(['chmod', 'a+rx', $denoPath]);
        $chmodProcess->run();

        // Cleanup
        @unlink($tempZip);

        // Verify installation
        $verifyProcess = new Process([$denoPath, '--version']);
        $verifyProcess->run();

        if ($verifyProcess->isSuccessful()) {
            $output = $verifyProcess->getOutput();
            if (preg_match('/deno (\S+)/', $output, $matches)) {
                Log::info("Successfully updated Deno to version {$matches[1]}");
            } else {
                Log::info('Successfully updated Deno');
            }

            return true;
        } else {
            Log::warning('Deno was installed but verification failed: '.$verifyProcess->getErrorOutput());

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

            // Download the latest nightly version
            $downloadProcess = new Process([
                'curl',
                '-L',
                'https://github.com/yt-dlp/yt-dlp-nightly-builds/releases/latest/download/yt-dlp',
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

        // Download the new nightly version to a temporary file
        $downloadProcess = new Process([
            'curl',
            '-L',
            'https://github.com/yt-dlp/yt-dlp-nightly-builds/releases/latest/download/yt-dlp',
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

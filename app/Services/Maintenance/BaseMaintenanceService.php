<?php

namespace App\Services\Maintenance;

use App\Settings\MaintenanceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base class for maintenance services providing common functionality
 */
abstract class BaseMaintenanceService
{
    /**
     * Send notification via email or webhook
     */
    protected function sendNotification(string $title, string $message, bool $success): void
    {
        try {
            $settings = app(MaintenanceSettings::class);

            if (! $settings->notifications_enabled) {
                return;
            }

            if ($success && ! $settings->notify_on_success) {
                return;
            }

            if (! $success && ! $settings->notify_on_failure) {
                return;
            }

            // Send email notification
            if ($settings->notification_email) {
                Log::info("Would send email to {$settings->notification_email}: {$title} - {$message}");
            }

            // Send webhook notification
            if ($settings->notification_webhook_url) {
                Http::post($settings->notification_webhook_url, [
                    'title' => $title,
                    'message' => $message,
                    'success' => $success,
                    'timestamp' => now()->toISOString(),
                    'app' => config('app.name'),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notification: '.$e->getMessage());
        }
    }
}

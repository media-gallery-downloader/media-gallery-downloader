<?php

namespace App\Helpers;

class FormatHelper
{
    /**
     * Format bytes into human-readable format
     */
    public static function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Parse human-readable bytes back to numeric value
     */
    public static function parseBytes(string $formatted): int
    {
        $units = ['TB' => 1024 ** 4, 'GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024, 'B' => 1];

        $formatted = strtoupper(trim($formatted));

        foreach ($units as $unit => $multiplier) {
            if (str_ends_with($formatted, $unit)) {
                $value = (float) trim(str_replace($unit, '', $formatted));

                return (int) ($value * $multiplier);
            }
        }

        return (int) $formatted;
    }
}

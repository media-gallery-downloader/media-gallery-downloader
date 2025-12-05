<?php

namespace Database\Factories;

use App\Models\FailedDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FailedDownload>
 */
class FailedDownloadFactory extends Factory
{
    protected $model = FailedDownload::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => fake()->url().'/video.mp4',
            'method' => fake()->randomElement(['direct', 'yt-dlp', 'auto']),
            'error_message' => fake()->sentence(),
            'status' => fake()->randomElement(['pending', 'retrying', 'failed', 'resolved']),
            'retry_count' => fake()->numberBetween(0, 3),
            'last_attempt_at' => fake()->optional()->dateTimeThisMonth(),
            'next_retry_at' => fake()->optional()->dateTimeThisMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the download is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the download has failed permanently.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'retry_count' => 3,
        ]);
    }

    /**
     * Indicate that the download has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
        ]);
    }
}

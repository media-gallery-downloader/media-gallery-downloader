<?php

namespace Database\Factories;

use App\Models\FailedUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FailedUpload>
 */
class FailedUploadFactory extends Factory
{
    protected $model = FailedUpload::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filename' => fake()->uuid().'.mp4',
            'mime_type' => 'video/mp4',
            'error_message' => fake()->sentence(),
            'status' => fake()->randomElement(['pending', 'failed', 'resolved']),
            'retry_count' => fake()->numberBetween(0, 3),
            'last_attempt_at' => fake()->optional()->dateTimeThisMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the upload is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the upload has failed permanently.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'retry_count' => 3,
        ]);
    }

    /**
     * Indicate that the upload has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
        ]);
    }
}

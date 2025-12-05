<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->uuid().'.mp4';

        return [
            'name' => $this->faker->sentence(3),
            'mime_type' => 'video/mp4',
            'size' => $this->faker->numberBetween(1000000, 100000000),
            'file_name' => $filename,
            'path' => 'media/'.$filename,
            'url' => '/storage/media/'.$filename,
            'source' => $this->faker->url(),
            'thumbnail_path' => null,
        ];
    }
}

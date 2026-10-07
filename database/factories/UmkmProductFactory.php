<?php

namespace Database\Factories;

use App\Models\Umkm;
use App\Models\UmkmProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UmkmProduct>
 */
class UmkmProductFactory extends Factory
{
    protected $model = UmkmProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'umkm_id' => Umkm::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => 'Rp ' . number_format(fake()->numberBetween(10000, 150000), 0, ',', '.'),
            'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999',
            'is_active' => true,
        ];
    }
}

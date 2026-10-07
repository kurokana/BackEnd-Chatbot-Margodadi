<?php

namespace Database\Factories;

use App\Enums\ServiceDomain;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Umkm>
 */
class UmkmFactory extends Factory
{
    protected $model = Umkm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ServiceCategory::factory()->state(['domain' => ServiceDomain::UMKM]),
            'reg_number' => 'MKD-UMKM-' . fake()->unique()->numerify('###'),
            'name' => 'UMKM ' . fake()->unique()->company(),
            'sub_title' => fake()->catchPhrase(),
            'owner_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'wa_number' => '628' . fake()->numerify('##########'),
            'address' => fake()->address(),
            'description' => fake()->paragraph(),
            'history' => fake()->paragraph(),
            'banner_image_url' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
            'gallery_urls' => [
                'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                'https://images.unsplash.com/photo-1540420773420-3366772f4999',
            ],
            'legal_certification' => 'P-IRT & Halal Kemenag',
            'legal_number' => 'ID' . fake()->numerify('##############'),
            'production_capacity' => '500 pcs/bulan',
            'capacity_note' => 'Menerima pesanan partai besar',
            'group_name' => 'Kelompok Usaha Bersama Margodadi',
            'group_location' => 'Dusun 1 RT 02 Margodadi',
            'map_title' => 'Lokasi Produksi',
            'map_address' => 'Pekon Margodadi, Kec. Ambarawa, Kab. Pringsewu',
            'map_url' => 'https://maps.google.com/?q=Margodadi',
            'last_verified_at' => now(),
            'is_active' => true,
        ];
    }
}

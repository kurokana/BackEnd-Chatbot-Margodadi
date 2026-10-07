<?php

namespace Database\Factories;

use App\Models\PublicService;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PublicService>
 */
class PublicServiceFactory extends Factory
{
    protected $model = PublicService::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'category_id' => ServiceCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'category_badge' => fake()->randomElement(['SURAT_PENGANTAR', 'ADMINISTRASI', 'FASILITAS']),
            'sub_category_badge' => fake()->randomElement(['Kependudukan', 'Sosial & Pendidikan', 'Perekonomian']),
            'description' => fake()->paragraph(),
            'legal_basis' => 'Peraturan Desa Margodadi No. 04 Tahun 2024',
            'requirements' => ['FC KTP Pemohon', 'FC Kartu Keluarga (KK)', 'Surat Pengantar RT/RW'],
            'steps' => [
                ['step' => 1, 'name' => 'Persiapan Berkas', 'desc' => 'Menyiapkan FC KTP dan KK', 'time' => '15 Menit', 'icon' => 'file-text'],
                ['step' => 2, 'name' => 'Pengajuan ke Loket', 'desc' => 'Menyerahkan berkas ke Balai Pekon', 'time' => '30 Menit', 'icon' => 'send'],
            ],
            'sla_duration' => '1 Hari Kerja',
            'cost_info' => 'Gratis (Rp 0)',
            'officer_in_charge' => 'Loket Kasi Pelayanan Pekon',
            'download_url' => 'https://margodadi.desa.id/downloads/blangko-layanan.pdf',
            'tags' => ['layanan', 'pekon', 'administrasi'],
            'is_active' => true,
        ];
    }
}

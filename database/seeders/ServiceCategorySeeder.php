<?php

namespace Database\Seeders;

use App\Enums\ServiceDomain;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Layanan Kependudukan & Administrasi',
                'slug' => 'layanan-kependudukan',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'icon' => 'id-card',
                'description' => 'Layanan pengurusan SKU, SKTM, KTP, KK, dan Surat Keterangan Desa',
                'is_active' => true,
            ],
            [
                'name' => 'Pemberdayaan & Potensi UMKM',
                'slug' => 'potensi-umkm',
                'domain' => ServiceDomain::UMKM,
                'icon' => 'storefront',
                'description' => 'Informasi pendaftaran, etalase, dan legalitas produk UMKM Pekon Margodadi',
                'is_active' => true,
            ],
            [
                'name' => 'Edukasi Pengelolaan Sampah',
                'slug' => 'edukasi-sampah',
                'domain' => ServiceDomain::WASTE_EDUCATION,
                'icon' => 'recycling',
                'description' => 'Program Bank Sampah Berkah, jadwal penimbangan, dan panduan komposting',
                'is_active' => true,
            ],
            [
                'name' => 'Informasi Publik & Pengaduan',
                'slug' => 'informasi-publik',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'icon' => 'info',
                'description' => 'Agenda kegiatan desa, posyandu, bantuan sosial, dan kontak darurat',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $data) {
            ServiceCategory::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}

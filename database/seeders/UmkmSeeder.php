<?php

namespace Database\Seeders;

use App\Enums\ServiceDomain;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use App\Models\UmkmProduct;
use Illuminate\Database\Seeder;

class UmkmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Kategori Makanan & Olahan
        $catKuliner = ServiceCategory::firstOrCreate(
            ['slug' => 'kuliner-olahan-pangan'],
            [
                'name' => 'Kuliner & Olahan Pangan',
                'domain' => ServiceDomain::UMKM,
                'icon' => 'utensils',
                'description' => 'Produk kuliner, camilan khas, keripik, dan makanan olahan warga Margodadi.',
                'is_active' => true,
            ]
        );

        // 2. Kategori Kerajinan & Anyaman
        $catKerajinan = ServiceCategory::firstOrCreate(
            ['slug' => 'kerajinan-kreatif'],
            [
                'name' => 'Kerajinan & Produk Kreatif',
                'domain' => ServiceDomain::UMKM,
                'icon' => 'gift',
                'description' => 'Kerajinan anyaman bambu, produk jahit/konveksi, dan cinderamata warga.',
                'is_active' => true,
            ]
        );

        // 3. Kategori Pertanian & Hasil Kebun
        $catPertanian = ServiceCategory::firstOrCreate(
            ['slug' => 'hasil-bumi-pertanian'],
            [
                'name' => 'Hasil Bumi & Pertanian',
                'domain' => ServiceDomain::UMKM,
                'icon' => 'sprout',
                'description' => 'Komoditas pertanian unggulan, bibit tanaman, dan pupuk organik pekon.',
                'is_active' => true,
            ]
        );

        // UMKM 1: Keripik Pisang
        $umkm1 = Umkm::firstOrCreate(
            ['reg_number' => 'MKD-UMKM-001'],
            [
                'category_id' => $catKuliner->category_id,
                'name' => 'Keripik Pisang Margodadi Berkibar',
                'sub_title' => 'Camilan Gurih Renyah Khas Pekon Margodadi',
                'owner_name' => 'Ibu Siti Munawaroh',
                'phone' => '081278901234',
                'wa_number' => '6281278901234',
                'address' => 'RT 03 Dusun 1 Pekon Margodadi, Kec. Ambarawa, Pringsewu',
                'description' => 'Produsen keripik pisang aneka rasa (Coklat, Keju, Susu, Balado, Moka) berbahan baku pisang kepok lokal pilihan langsung dari petani Margodadi.',
                'history' => 'Berdiri sejak tahun 2019, berawal dari kelompok ibu-ibu PKK dusun yang memanfaatkan melimpahnya hasil panen pisang pekon.',
                'banner_image_url' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                    'https://images.unsplash.com/photo-1540420773420-3366772f4999',
                ],
                'legal_certification' => 'P-IRT & Sertifikasi Halal Kemenag',
                'legal_number' => 'P-IRT 2151810010045-26',
                'production_capacity' => '800 Pouch / Bulan',
                'capacity_note' => 'Siap melayani pesanan souvenir hajatan dan kemitraan toko oleh-oleh',
                'group_name' => 'KWT Melati Indah Margodadi',
                'group_location' => 'Balai Dusun 1 Margodadi',
                'map_title' => 'Rumah Produksi Keripik Pisang',
                'map_address' => 'Jl. Poros Desa No. 12 Dusun 1 Margodadi',
                'map_url' => 'https://maps.google.com/?q=Margodadi+Pringsewu',
                'last_verified_at' => now(),
                'is_active' => true,
            ]
        );

        UmkmProduct::firstOrCreate(
            ['umkm_id' => $umkm1->umkm_id, 'name' => 'Keripik Pisang Coklat Lumer 200g'],
            [
                'description' => 'Keripik pisang kepok renyah berlapis coklat leleh premium khas Lampung.',
                'price' => 'Rp 18.000',
                'image_url' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                'is_active' => true,
            ]
        );

        UmkmProduct::firstOrCreate(
            ['umkm_id' => $umkm1->umkm_id, 'name' => 'Keripik Pisang Gurih Manis Original 250g'],
            [
                'description' => 'Varian original gurih dan manis renyah alami.',
                'price' => 'Rp 15.000',
                'image_url' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                'is_active' => true,
            ]
        );

        // UMKM 2: Anyaman Bambu
        $umkm2 = Umkm::firstOrCreate(
            ['reg_number' => 'MKD-UMKM-002'],
            [
                'category_id' => $catKerajinan->category_id,
                'name' => 'Anyaman Bambu Kreatif Lestari',
                'sub_title' => 'Kerajinan Besek, Tudung Saji & Hampers Etnik Ramah Lingkungan',
                'owner_name' => 'Bapak Slamet Raharjo',
                'phone' => '082188990011',
                'wa_number' => '6282188990011',
                'address' => 'RT 05 Dusun 2 Pekon Margodadi',
                'description' => 'Pusat pembuatan kerajinan anyaman bambu tradisional dan modern seperti besek hantaran, keranjang buah, tudung saji, dan tempat tisu.',
                'history' => 'Keahlian turun-temurun warga dusun 2 yang terus berinovasi menghasilkan produk ramah lingkungan pengganti plastik.',
                'banner_image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1540420773420-3366772f4999',
                ],
                'legal_certification' => 'NIB Resmi KBLI Kerajinan',
                'legal_number' => 'NIB 0220202930129',
                'production_capacity' => '400 pcs / Bulan',
                'capacity_note' => 'Menerima pesanan custom ukuran dan warna',
                'group_name' => 'Paguyuban Pengrajin Bambu Margodadi',
                'group_location' => 'Dusun 2 Margodadi',
                'map_title' => 'Sanggar Anyaman Bambu Lestari',
                'map_address' => 'Dusun 2 RT 05 Pekon Margodadi',
                'map_url' => 'https://maps.google.com/?q=Margodadi+Pringsewu',
                'last_verified_at' => now(),
                'is_active' => true,
            ]
        );

        UmkmProduct::firstOrCreate(
            ['umkm_id' => $umkm2->umkm_id, 'name' => 'Besek Anyaman Bambu Hantaran (Set isi 3)'],
            [
                'description' => 'Besek bambu halus estetik untuk hampers lebaran atau souvenir pernikahan.',
                'price' => 'Rp 35.000',
                'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999',
                'is_active' => true,
            ]
        );
    }
}

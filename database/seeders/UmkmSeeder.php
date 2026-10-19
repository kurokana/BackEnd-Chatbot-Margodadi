<?php

namespace Database\Seeders;

use App\Enums\ServiceDomain;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use App\Models\UmkmProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class UmkmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('data/real_umkms.json');
        if (!File::exists($jsonPath)) {
            $this->command->error("File real_umkms.json tidak ditemukan di: {$jsonPath}");
            return;
        }

        $records = json_decode(File::get($jsonPath), true);
        if (!is_array($records)) {
            $this->command->error("Format JSON pada real_umkms.json tidak valid.");
            return;
        }

        // Kategori UMKM Utama
        $defaultCat = ServiceCategory::firstOrCreate(
            ['slug' => 'potensi-umkm'],
            [
                'name' => 'Pemberdayaan & Potensi UMKM',
                'domain' => ServiceDomain::UMKM,
                'icon' => 'storefront',
                'description' => 'Direktori potensi dan pelaku usaha mikro, kecil, dan menengah Pekon Margodadi.',
                'is_active' => true,
            ]
        );

        // Petakan kategori umum
        $categoryCache = [];

        foreach ($records as $index => $rec) {
            $categoryName = trim($rec['category'] ?: 'Pemberdayaan & Potensi UMKM');
            $categorySlug = \Illuminate\Support\Str::slug($categoryName);

            if (!isset($categoryCache[$categorySlug])) {
                $categoryCache[$categorySlug] = ServiceCategory::firstOrCreate(
                    ['slug' => $categorySlug],
                    [
                        'name' => $categoryName,
                        'domain' => ServiceDomain::UMKM,
                        'icon' => 'storefront',
                        'description' => "Kategori usaha {$categoryName} di Pekon Margodadi.",
                        'is_active' => true,
                    ]
                );
            }

            $cat = $categoryCache[$categorySlug] ?? $defaultCat;
            $dusun = trim($rec['dusun'] ?: 'Margodadi');
            $name = trim($rec['name']);
            $idStr = trim($rec['id'] ?: sprintf('UMKM-%03d', $index + 1));
            $productsRaw = trim($rec['products'] ?: '');
            $ownerContact = trim($rec['owner_contact'] ?: '');
            $locationDetail = trim($rec['location_detail'] ?: '');
            $embeddingDoc = trim($rec['embedding_document'] ?: '');

            // Ekstrak nama pemilik & telepon jika tersedia
            $ownerName = 'Warga ' . $dusun;
            $phoneNumber = null;

            if ($ownerContact !== '') {
                if (preg_match('/(08\d{8,12}|\+62\d{8,12})/', $ownerContact, $phoneMatches)) {
                    $phoneNumber = $phoneMatches[1];
                    $cleanedName = trim(str_replace($phoneNumber, '', $ownerContact), " -:,()");
                    if (!empty($cleanedName)) {
                        $ownerName = $cleanedName;
                    }
                } else {
                    $ownerName = $ownerContact;
                }
            }

            $fullAddress = "Dusun {$dusun}" . ($locationDetail ? ", {$locationDetail}" : "") . ", Pekon Margodadi, Kec. Ambarawa, Kab. Pringsewu";

            $umkm = Umkm::updateOrCreate(
                ['reg_number' => "MGD-{$idStr}"],
                [
                    'category_id' => $cat->category_id,
                    'name' => $name,
                    'sub_title' => $productsRaw ?: "Usaha {$categoryName}",
                    'owner_name' => $ownerName,
                    'phone' => $phoneNumber,
                    'wa_number' => $phoneNumber,
                    'address' => $fullAddress,
                    'description' => $embeddingDoc ?: "Usaha {$name} bergerak di bidang {$categoryName}. Produk atau layanan: {$productsRaw}. Berlokasi di Dusun {$dusun}, Pekon Margodadi.",
                    'history' => "Unit usaha ekonomi masyarakat yang beroperasi di Dusun {$dusun}, Pekon Margodadi, terdata dalam inventarisasi potensi desa.",
                    'legal_certification' => $rec['verification_status'] === 'Terverifikasi' ? 'NIB / Surat Keterangan Usaha Desa' : 'Dalam Proses Pendataan',
                    'production_capacity' => 'Skala Mikro & Rumah Tangga',
                    'group_name' => "Paguyuban Usaha Dusun {$dusun}",
                    'group_location' => "Dusun {$dusun}",
                    'map_title' => "Lokasi Usaha {$name}",
                    'map_address' => $fullAddress,
                    'last_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            // Seed produk-produk
            if ($productsRaw) {
                $items = array_filter(array_map('trim', preg_split('/[;,]/', $productsRaw)));
                foreach ($items as $item) {
                    if (empty($item)) continue;
                    if (preg_match('/(belum diketahui|usaha patungan)/i', $item)) continue;
                    UmkmProduct::firstOrCreate(
                        [
                            'umkm_id' => $umkm->umkm_id,
                            'name' => ucfirst($item),
                        ],
                        [
                            'description' => "Produk atau layanan {$item} dari {$name} di Dusun {$dusun}.",
                            'price' => 'Hubungi Penjual',
                            'is_active' => true,
                        ]
                    );
                }
            } else {
                UmkmProduct::firstOrCreate(
                    [
                        'umkm_id' => $umkm->umkm_id,
                        'name' => $name,
                    ],
                    [
                        'description' => "Layanan dan produk usaha {$name} di Dusun {$dusun}.",
                        'price' => 'Hubungi Penjual',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}

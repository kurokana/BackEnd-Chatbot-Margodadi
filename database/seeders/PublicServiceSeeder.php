<?php

namespace Database\Seeders;

use App\Enums\ServiceDomain;
use App\Models\PublicService;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class PublicServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Kategori Layanan Administrasi & Kependudukan
        $catKependudukan = ServiceCategory::firstOrCreate(
            ['slug' => 'administrasi-kependudukan'],
            [
                'name' => 'Administrasi & Kependudukan',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'icon' => 'id-card',
                'description' => 'Layanan pembuatan surat pengantar KTP, KK, Akta, dan Keterangan Domisili.',
                'is_active' => true,
            ]
        );

        // 2. Kategori Layanan Usaha & Perekonomian
        $catUsaha = ServiceCategory::firstOrCreate(
            ['slug' => 'usaha-perekonomian'],
            [
                'name' => 'Usaha & Perekonomian',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'icon' => 'store',
                'description' => 'Layanan surat perizinan usaha, rekomendasi KUR, dan legalitas UMKM pekon.',
                'is_active' => true,
            ]
        );

        // 3. Kategori Layanan Sosial & Bantuan
        $catSosial = ServiceCategory::firstOrCreate(
            ['slug' => 'sosial-kesejahteraan'],
            [
                'name' => 'Sosial & Kesejahteraan',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'icon' => 'users',
                'description' => 'Layanan Surat Keterangan Tidak Mampu (SKTM), beasiswa, dan bantuan sosial.',
                'is_active' => true,
            ]
        );

        // Layanan 1: SKU
        PublicService::firstOrCreate(
            ['slug' => 'surat-keterangan-usaha-sku'],
            [
                'category_id' => $catUsaha->category_id,
                'title' => 'Surat Keterangan Usaha (SKU)',
                'category_badge' => 'SURAT_PENGANTAR',
                'sub_category_badge' => 'Perekonomian & UMKM',
                'description' => 'Surat keterangan resmi dari Pekon Margodadi yang menyatakan kepemilikan dan keberadaan usaha aktif warga untuk pengajuan pinjaman bank/KUR atau legalitas usaha.',
                'legal_basis' => 'Peraturan Desa Margodadi No. 03 Tahun 2023 tentang Pelayanan Administrasi Usaha Desa',
                'requirements' => [
                    'Fotokopi KTP Pemohon (wajib berdomisili Margodadi)',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat Pengantar dari Ketua RT setempat',
                    'Foto dokumentasi tempat usaha / foto produk',
                ],
                'steps' => [
                    ['step' => 1, 'name' => 'Pengantar RT', 'desc' => 'Meminta surat pengantar dari Ketua RT tempat tinggal', 'time' => '15 Menit', 'icon' => 'user-check'],
                    ['step' => 2, 'name' => 'Verifikasi Loket', 'desc' => 'Menyerahkan berkas persyaratan ke Loket Pelayanan Balai Pekon', 'time' => '10 Menit', 'icon' => 'file-text'],
                    ['step' => 3, 'name' => 'Pemeriksaan & Cetak', 'desc' => 'Petugas memvalidasi data dan mencetak draf SKU', 'time' => '15 Menit', 'icon' => 'printer'],
                    ['step' => 4, 'name' => 'Penandatanganan', 'desc' => 'Penandatanganan oleh Kepala Pekon/Sekretaris Pekon', 'time' => '30 Menit', 'icon' => 'pen-tool'],
                    ['step' => 5, 'name' => 'Pengambilan Surat', 'desc' => 'Surat siap diambil tanpa dipungut biaya', 'time' => '5 Menit', 'icon' => 'check-circle'],
                ],
                'sla_duration' => '1 Hari Kerja (Maksimal 24 Jam)',
                'cost_info' => 'Gratis (Rp 0)',
                'officer_in_charge' => 'Loket Kasi Pelayanan Pekon Margodadi',
                'download_url' => 'https://margodadi.desa.id/formulir/sku-template.pdf',
                'tags' => ['sku', 'usaha', 'umkm', 'kur', 'bank', 'surat usaha', 'modal'],
                'is_active' => true,
            ]
        );

        // Layanan 2: SKTM
        PublicService::firstOrCreate(
            ['slug' => 'surat-keterangan-tidak-mampu-sktm'],
            [
                'category_id' => $catSosial->category_id,
                'title' => 'Surat Keterangan Tidak Mampu (SKTM)',
                'category_badge' => 'SURAT_PENGANTAR',
                'sub_category_badge' => 'Sosial & Pendidikan',
                'description' => 'Surat pengantar untuk keperluan permohonan beasiswa sekolah/kuliah, keringanan biaya pengobatan rumah sakit (BPJS PBI), atau program bantuan sosial.',
                'legal_basis' => 'Keputusan Kepala Pekon Margodadi tentang Kriteria Keluarga Pra-Sejahtera',
                'requirements' => [
                    'Fotokopi KTP Pemohon / Orang Tua',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat Pengantar RT yang diketahui RW',
                    'Surat Keterangan Penghasilan / Keterangan Sekolah/Kampus (bila untuk pendidikan)',
                ],
                'steps' => [
                    ['step' => 1, 'name' => 'Pengantar RT/RW', 'desc' => 'Meminta surat pengantar dari RT/RW setempat', 'time' => '15 Menit', 'icon' => 'user-check'],
                    ['step' => 2, 'name' => 'Pendaftaran di Balai Pekon', 'desc' => 'Menyerahkan berkas ke Kasi Kesejahteraan', 'time' => '10 Menit', 'icon' => 'file-text'],
                    ['step' => 3, 'name' => 'Verifikasi Data DTKS', 'desc' => 'Pengecekan kesesuaian data warga dengan basis data sosial', 'time' => '20 Menit', 'icon' => 'database'],
                    ['step' => 4, 'name' => 'Penerbitan SKTM', 'desc' => 'Penerbitan dan legalisasi surat oleh Kepala Pekon', 'time' => '30 Menit', 'icon' => 'pen-tool'],
                ],
                'sla_duration' => '1 Hari Kerja',
                'cost_info' => 'Gratis (Rp 0)',
                'officer_in_charge' => 'Loket Kasi Kesejahteraan Pekon',
                'download_url' => 'https://margodadi.desa.id/formulir/sktm-template.pdf',
                'tags' => ['sktm', 'tidak mampu', 'beasiswa', 'kip', 'bpjs', 'bantuan sosial', 'kesehatan'],
                'is_active' => true,
            ]
        );

        // Layanan 3: Surat Keterangan Domisili
        PublicService::firstOrCreate(
            ['slug' => 'surat-keterangan-domisili-warga'],
            [
                'category_id' => $catKependudukan->category_id,
                'title' => 'Surat Keterangan Domisili Warga',
                'category_badge' => 'ADMINISTRASI',
                'sub_category_badge' => 'Kependudukan',
                'description' => 'Surat keterangan domisili atau tempat tinggal resmi warga perseorangan di wilayah Pekon Margodadi untuk urusan perbankan, pekerjaan, atau instansi resmi.',
                'legal_basis' => 'Undang-Undang Administrasi Kependudukan',
                'requirements' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat Pengantar RT setempat',
                    'Pas foto ukuran 3x4 (1 lembar jika diperlukan)',
                ],
                'steps' => [
                    ['step' => 1, 'name' => 'Pengantar RT', 'desc' => 'Meminta pengantar RT', 'time' => '10 Menit', 'icon' => 'user-check'],
                    ['step' => 2, 'name' => 'Pelayanan Balai Pekon', 'desc' => 'Verifikasi dan cetak surat di loket pelayanan', 'time' => '15 Menit', 'icon' => 'file-text'],
                ],
                'sla_duration' => '30 Menit (Langsung Jadi)',
                'cost_info' => 'Gratis (Rp 0)',
                'officer_in_charge' => 'Loket Kasi Pemerintahan Pekon',
                'download_url' => null,
                'tags' => ['domisili', 'tempat tinggal', 'kependudukan', 'ktp', 'alamat'],
                'is_active' => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Enums\ServiceDomain;
use App\Models\KbChunk;
use App\Models\KbDocument;
use Illuminate\Database\Seeder;

class KbDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documents = [
            // 1. Visi & Misi
            [
                'title' => 'Visi, Misi, dan Arah Pembangunan Pekon Margodadi',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/artikel/2020/03/29/visi-dan-misi',
                'validator' => 'Kepala Pekon Margodadi (Didi Handoko)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Visi resmi Pekon Margodadi: 'Pekon Margodadi yang Bersih, Anggun, Damai, Agamis dan Bersatu'. Visi ini menjadi pedoman utama seluruh arah kebijakan pembangunan desa, pemberdayaan masyarakat, serta pelayanan aparatur pekon.",
                    "Misi pembangunan ekonomi & infrastruktur Pekon Margodadi:\n1. Mengembangkan usaha pertanian dan peternakan dengan menggunakan Teknologi Tepat Guna (TTG).\n2. Mengembangkan usaha industri pengrajin batu bata modern.\n3. Mengupayakan penyediaan bibit unggul untuk pertanian.\n4. Meningkatkan infrastruktur jalan umum dan jalan usaha tani pedesaan.\n5. Program perbaikan rumah sehat / layak huni untuk keluarga miskin.",
                    "Misi pendidikan, kesehatan & aparatur Pekon Margodadi:\n1. Peningkatan sarana dan prasarana pendidikan.\n2. Peningkatan sarana dan prasarana kesehatan masyarakat.\n3. Meningkatkan keterampilan dan pelatihan kerja masyarakat.\n4. Peningkatan kapasitas masyarakat di bidang pertanian, perikanan, dan peternakan.\n5. Peningkatan kapasitas pengelolaan permodalan usaha desa.\n6. Peningkatan kualitas pelayanan kesehatan masyarakat.\n7. Peningkatan kapasitas dan kuantitas aparatur pemerintahan pekon.\n8. Meningkatkan kesadaran masyarakat tentang kebersihan lingkungan.\n9. Peningkatan sarana prasarana peribadatan dan kesehatan jasmani-rohani.\n10. Meningkatkan kesadaran masyarakat tentang pentingnya kamtibmas.",
                ],
            ],

            // 2. Pemerintah Pekon & Struktur Aparatur
            [
                'title' => 'Pemerintah Pekon Margodadi dan Struktur Aparatur Desa',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/artikel/2020/01/28/pemerintah-pekon',
                'validator' => 'Sekretaris Pekon (Ajung Purna Irawan)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Pemerintahan Pekon Margodadi dipimpin oleh Kepala Pekon (Kakon) Bpk. Didi Handoko dan Sekretaris Pekon (Sekdes) Bpk. Ajung Purna Irawan. Kantor Balai Pekon Margodadi beralamat di Jalan Raya Margodadi, Kecamatan Ambarawa, Kabupaten Pringsewu, Lampung (Kode Pos 35376, Telp/WA Operator: 0821-7516-7314, Email: Pekonmargodadi23@gmail.com).",
                    "Susunan Kepala Urusan (Kaur) dan Kepala Seksi (Kasi) Pekon Margodadi:\n- Kaur Perencanaan: Fitri Anggeraeni\n- Kaur Keuangan: Widi Hartono\n- Kaur Tata Usaha dan Umum: Fatmawati\n- Kasi Pemerintahan: Didi Gunari\n- Kasi Kesejahteraan (Kesra): Asih Lestari\n- Kasi Pelayanan: Sugiono",
                    "Pekon Margodadi terbagi menjadi 4 Wilayah Dusun yang dipimpin oleh Kepala Dusun (Kadus):\n1. Kadus 001 (Dusun Sumberdadi): Muhammad Nur Sodiq\n2. Kadus 002 (Dusun Margorejo): Darmawan\n3. Kadus 003 (Dusun Margodadi): Deki Hariyanto\n4. Kadus 004 (Dusun Bulurejo): Suherman",
                ],
            ],

            // 3. Badan Hippun Pemekonan (BHP)
            [
                'title' => 'Badan Hippun Pemekonan (BHP) Pekon Margodadi Periode 2020-2026',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/artikel/2020/03/29/badan-hippun-pemekonan-bhp',
                'validator' => 'Ketua BHP Margodadi (Misino)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Badan Hippun Pemekonan (BHP) adalah lembaga legislatif dan permusyawaratan desa di Pekon Margodadi yang berkedudukan sebagai perwujudan demokrasi dalam penyelenggaraan pemerintahan pekon. BHP berfungsi membahas dan menyepakati rancangan Peraturan Pekon bersama Kepala Pekon, menampung dan menyalurkan aspirasi masyarakat, serta melakukan pengawasan terhadap kinerja Kepala Pekon dan pelaksanaan APBDes.",
                    "Susunan Pengurus dan Anggota BHP Pekon Margodadi Periode 2020-2026 (9 Anggota):\n1. Ketua: MISINO (Perwakilan Dusun Bulurejo)\n2. Wakil Ketua: SUPOYO RAHARDI (Perwakilan Dusun Margorejo)\n3. Sekretaris: MARADONA (Perwakilan Dusun Margorejo)\n4. Anggota: SUPONO (Dusun Bulurejo)\n5. Anggota: YATIN SELAMET (Dusun Margorejo)\n6. Anggota: SUWANDI (Dusun Margorejo)\n7. Anggota: SUBARKAH AZZUHRI (Dusun Sumberdadi)\n8. Anggota: MAD TOYIB (Dusun Sumberdadi)\n9. Anggota: WAHIDIN (Dusun Margodadi)",
                    "Hak dan Wewenang BHP Pekon Margodadi: BHP berhak mengawasi pelaksanaan Peraturan Desa dan APBDes, meminta keterangan kepada Pemerintah Pekon terkait tata kelola pemerintahan, membentuk panitia pemilihan kepala pekon, memberi persetujuan pengangkatan/pemberhentian perangkat desa, serta menyusun tata tertib BHP.",
                ],
            ],

            // 4. Tupoksi Kepala Desa dan Perangkat Desa
            [
                'title' => 'Tugas Pokok dan Fungsi (Tupoksi) Kepala Desa dan Perangkat Desa Margodadi',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/artikel/2014/04/30/tupoksi-kepala-desa-dan-perangkat-desa',
                'validator' => 'Kasi Pemerintahan (Didi Gunari)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Tupoksi Kepala Pekon (Didi Handoko): Memimpin penyelenggaraan pemerintahan desa, memegang kekuasaan pengelolaan keuangan dan aset desa, menetapkan Peraturan Desa dan APBDes, memelihara ketenteraman dan ketertiban masyarakat, serta mengoordinasikan pembangunan partisipatif.\n\nTupoksi Sekretaris Pekon (Ajung Purna Irawan): Memimpin sekretariat desa, membantu Kepala Pekon dalam urusan administrasi pemerintahan, tata naskah dinas, kearsipan, penyusunan RAPBDes, ekspedisi, serta verifikasi laporan pertanggungjawaban keuangan.",
                    "Tupoksi Kepala Urusan (Kaur) Pekon Margodadi:\n1. Kaur Tata Usaha dan Umum (Fatmawati): Mengelola surat-menyurat, tata naskah dinas, arsiparis, inventarisasi aset dan sarana kantor pekon, penyiapan rapat dinas, dan perjalanan dinas.\n2. Kaur Keuangan (Widi Hartono): Melaksanakan pembukuan keuangan desa, penatausahaan pendapatan dan belanja APBDes, serta penyusunan SPJ keuangan.\n3. Kaur Perencanaan (Fitri Anggeraeni): Mengoordinasikan perencanaan pembangunan desa, inventarisasi data pembangunan, monitoring evaluasi program, serta penyusunan RPJMDes dan RKPDes.",
                    "Tupoksi Kepala Seksi (Kasi) dan Kepala Dusun (Kadus) Margodadi:\n1. Kasi Pemerintahan (Didi Gunari): Mengelola tata praja desa, regulasi peraturan desa, pembinaan masalah pertanahan (sporadik/waris), ketertiban masyarakat/satlinmas, administrasi kependudukan, dan pengelolaan profil desa.\n2. Kasi Kesejahteraan (Asih Lestari): Mengelola sosialisasi dan pemberdayaan sosial budaya, ekonomi warga, lingkungan hidup, pemuda/karang taruna, dan program bantuan sosial.\n3. Kasi Pelayanan (Sugiono): Melaksanakan pelayanan nikah/rujuk (NTCR), pencatatan kelahiran dan kematian, serta pembangunan sarana pendidikan dan kesehatan.\n4. Kepala Dusun (Kadus): Membina ketenteraman wilayah dusun, mengawasi pelaksanaan pembangunan dusun, dan memfasilitasi mobilitas warga di dusun masing-masing.",
                ],
            ],

            // 5. Pembagian Bidang Kegiatan APBDes
            [
                'title' => 'Pembagian Bidang Kegiatan dan Tata Kelola Anggaran APBDes Margodadi',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/artikel/2021/04/04/pembagian-bidang-kegiatan-apbdes',
                'validator' => 'Kaur Keuangan (Widi Hartono)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Penyelenggaraan tata kelola Anggaran Pendapatan dan Belanja Desa (APBDes) Pekon Margodadi dibagi ke dalam 5 Bidang Kegiatan Utama:\n1. Bidang Penyelenggaraan Pemerintahan Desa (penghasilan tetap & tunjangan aparatur/BHP, operasional kantor, pendataan kependudukan).\n2. Bidang Pelaksanaan Pembangunan Desa (pembangunan infrastruktur jalan usaha tani, drainase, rabat beton, perbaikan rumah tidak layak huni/Rutilahu).\n3. Bidang Pembinaan Kemasyarakatan (kegiatan kamtibmas Satlinmas, pembinaan pemuda, keagamaan, seni budaya, dan kelembagaan PKK).\n4. Bidang Pemberdayaan Masyarakat (pelatihan kelompok tani, bantuan bibit pertanian, bantuan teknologi tepat guna, penguatan modal UMKM).\n5. Bidang Penanggulangan Bencana, Keadaan Darurat dan Mendesak (penyaluran BLT Dana Desa dan mitigasi darurat).",
                    "Pelaksana Kegiatan Anggaran (PPKD) di Pekon Margodadi dijalankan oleh Sekretaris Pekon selaku koordinator, didukung oleh masing-masing Kepala Seksi (Kasi) dan Kepala Urusan (Kaur) sesuai bidang kegiatannya secara transparan, akuntabel, dan dilaporkan berkala di Balai Pekon dan situs resmi desa.",
                ],
            ],

            // 6. LKMD (Lembaga Kemasyarakatan Desa) dan Turunannya
            [
                'title' => 'Lembaga Kemasyarakatan Desa (LKMD) Pekon Margodadi dan Susunan Pengurus',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/data-lembaga/',
                'validator' => 'Kasi Kesra (Asih Lestari)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Lembaga Kemasyarakatan Desa (LKMD) di Pekon Margodadi merupakan wadah partisipasi masyarakat dalam pembangunan dan pelayanan sosial. Lembaga-lembaga resmi yang aktif meliputi:\n1. Tim Penggerak PKK: Ketua Marsel Diana Sari (Margorejo), Wakil Ketua Yuni Fitriyani (Margodadi), Sekretaris Septiana (Margodadi), Bendahara Sri Setiti (Bulurejo).\n2. Karang Taruna: Ketua Triyadi (Margodadi), Wakil Ketua Mulyono (Margodadi), Sekretaris Peni Ambarwati (Margodadi), Bendahara Firman Sigit Santoso (Bulurejo).\n3. LPM (Lembaga Pemberdayaan Masyarakat): Ketua Sarjono (Sumberdadi), Wakil Ketua Arisno (Bulurejo), Sekretaris Prayitno (Margodadi), Bendahara Juremi (Margodadi).",
                    "Lembaga Pelayanan Sosial & Keamanan LKMD Margodadi:\n1. Pos Kesejahteraan Sosial (Poskesos 'Madani'): Ketua Heri Purnomo (Margodadi RT 011), Wakil Ketua Asih Lestari (Margorejo RT 008), Sekretaris Supriyadi (Margodadi RT 012). Bertugas dalam pendataan, verifikasi faktual DTKS, dan fasilitasi bantuan sosial bagi keluarga miskin.\n2. Satuan Perlindungan Masyarakat (Satlinmas): Ketua Abdul Wahab Lihawa (Margorejo RT 005), bertugas menjaga ketenteraman dan ketertiban lingkungan umum di 4 dusun.\n3. Rukun Warga (RW) dan Rukun Tetangga (RT): Tersebar di 4 RW dan 15 RT se-Pekon Margodadi.",
                    "Jaringan Posyandu Balita & Posbindu Kesehatan di Pekon Margodadi:\n- Posyandu Kenari (Dusun Sumberdadi RT 001): Ketua Yustina, Sekretaris Siti Nur Karomah.\n- Posyandu Cendrawasih (Dusun Margorejo RT 006): Ketua Agustina Nur Cahyani, Sekretaris Sumiati.\n- Posyandu Pipit (Dusun Margorejo RT 007): Ketua Rusmiyati, Sekretaris Kustini.\n- Posyandu Nuri (Dusun Margodadi RT 009): Sekretaris Misinah.\n- Posyandu Merpati (Dusun Margodadi RT 011): Ketua Sri Handayani, Sekretaris Sri Wahyuni.\n- Posyandu Rajawali (Dusun Bulurejo RT 013): Ketua Tursilah, Sekretaris Wagiyanti.\n- Posyandu Lansia: Ketua Yuni Fitriyani, Sekretaris Badriyah.\n- Posyandu Remaja: Ketua Mira Eviana, Sekretaris Vega Nindi Larasati.\n- Posbindu Kutilang: Ketua Sujilah, Sekretaris Sumiati, Bendahara Sri Setiti.",
                ],
            ],

            // 7. Profil Desa & Sejarah
            [
                'title' => 'Profil Wilayah, Luas Lahan, Batas, dan Sejarah Pekon Margodadi',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/artikel/2020/03/29/sejarah-pekon-margodadi',
                'validator' => 'Kepala Pekon Margodadi (Didi Handoko)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Sejarah Pembukaan Pekon Margodadi: Dahulu wilayah Margodadi merupakan hutan belukar dan daerah rawa. Pada tahun 1931 dibuka pertama kali oleh rombongan transmigran sebanyak kurang lebih 50 KK asal Jawa Tengah dan Jawa Timur dipimpin oleh Kepala Tebang bernama Bapak Japan. Pada tahun 1932 resmi diakui sebagai perkampungan pedesaan dengan nama 'DESA MARGODADI' yang terdiri dari 4 dusun: Sumberdadi, Margorejo, Margodadi, dan Bulurejo.",
                    "Silsilah Kepemimpinan Kepala Desa Margodadi dari masa ke masa:\n1. 1932-1942: Bp. Japan (Sekdes: Rahmat / Atmo Suwarto)\n2. 1942-1950: Bp. Sukadis (Sekdes: Rahmat / Atmo Suwarto)\n3. 1950-1958: Bp. Nuryakin (Sekdes: Basiran)\n4. 1958-1965: Bp. Joyo Diyono (Sekdes: Siswo Utomo)\n5. 1965-1967: Bp. Mad Saeri (PJS)\n6. 1967-1970: Bp. Sujangi (PJS) (Sekdes: Muslim)\n7. 1970-1973: Bp. Bardan (Sekdes: Sukirman)\n8. 1973-1979: Bp. Jumadi Abu (Sekdes: Sanudin Yusup)\n9. 1979-1987: Bp. Suwito (Sekdes: Sanudin Yusup)\n10. 1987-1997: Bp. Subur Junaedi (Sekdes: Sanudin Yusup)\n11. 1997-2006: Bp. Mamex Subiyanto (Sekdes: Makhalin)\n12. 2006-2018: Bp. Hi. Syamsuddin (Sekdes: Makhalin)\n13. 2018: Bp. Indra Marki Anggara S. (PJS)\n14. 2018-sekarang: Bp. Didi Handoko (Sekdes: Ajung Purna Irawan)",
                    "Batas Wilayah Geografis Pekon Margodadi:\n- Sebelah Utara: Pekon Margakaya dan Pekon Waluyojati\n- Sebelah Timur: Pekon Parerejo dan Pekon Blitarejo\n- Sebelah Selatan: Pekon Ambarawa Timur dan Pekon Ambarawa\n- Sebelah Barat: Pekon Jati Agung\nKoordinat Balai Pekon Margodadi terletak pada Latitude -5.38693 dan Longitude 104.96542.",
                    "Penggunaan dan Luas Wilayah Pekon Margodadi (Total Luas 529,20 Hektar):\n- Luas Tanah Sawah (tadah hujan): 296,00 Ha\n- Luas Tanah Kering: 227,00 Ha (Pemukiman 184 Ha, Pekarangan 37 Ha, Tegal/Ladang 6 Ha)\n- Luas Fasilitas Umum: 6,20 Ha (Lapangan Olahraga 1,20 Ha, Perkantoran Pekon 0,17 Ha, Pemakaman Umum 0,90 Ha, Sekolah 0,60 Ha, Pertokoan/Pasar 1,48 Ha, Jalan 1,30 Ha, Perikanan 0,25 Ha, Sutet 0,30 Ha).",
                    "Statistik Kependudukan Resmi Pekon Margodadi (Tercatat 2026):\n- Jumlah Penduduk: 5.221 jiwa (Laki-laki: 2.685 jiwa, Perempuan: 2.536 jiwa)\n- Jumlah Kepala Keluarga (KK): 1.610 KK\n- Komposisi Agama: Islam 4.839 jiwa, Katolik 214 jiwa, Hindu 98 jiwa, Kristen 70 jiwa.\n- Mata Pencaharian Dominan: Petani/Pekebun (822 orang), Buruh Harian Lepas (391 orang), Wiraswasta (386 orang), Buruh Tani (94 orang), Karyawan Swasta (72 orang), PNS (69 orang).",
                ],
            ],

            // 8. Bantuan Sosial (Bansos) & Kesejahteraan
            [
                'title' => 'Panduan Program Bantuan Sosial (PKH, BPNT, BLT-DD, Rutilahu, BPJS, Kursi Roda, Beras CPP, dan Anomali DTKS)',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'https://www.margodadi-pringsewu.desa.id/first/statistik/502',
                'validator' => 'Ketua Poskesos Madani (Heri Purnomo)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Katalog Program Bantuan Sosial (Bansos) yang berlaku di Pekon Margodadi:\n1. PKH (Program Keluarga Harapan): Bantuan bersyarat bagi keluarga sangat miskin/rentan yang memiliki komponen ibu hamil, balita, anak sekolah (SD-SMA), disabilitas berat, atau lansia.\n2. BPNT (Bantuan Pangan Non Tunai / Program Sembako): Bantuan reguler untuk pembelian sembako bergizi.\n3. BLT Dana Desa (BLT-DD Tahap I & Tahap II): Bantuan tunai penanggulangan kemiskinan ekstrem yang dianggarkan langsung dari Dana Desa Margodadi.\n4. RUTILAHU (Rehabilitasi Rumah Tidak Layak Huni): Bantuan bedah rumah sehat bagi keluarga prasejahtera.\n5. BPJS PBI (Penerima Bantuan Iuran): Fasilitasi jaminan kesehatan gratis ditanggung APBN/APBD.\n6. Bantuan Alat Bantu / Kursi Roda: Bantuan khusus bagi warga lansia dan penyandang disabilitas.\n7. Bantuan Pangan Beras CPP Bulog: Penyaluran cadangan pangan pemerintah.",
                    "Tata Cara dan Tempat Pengambilan Bantuan Sosial:\n- Seluruh pembagian bantuan sosial secara massal dipusatkan di Gedung Serba Guna (GSG) Pekon Margodadi atau Lapangan Balai Pekon.\n- Persyaratan Wajib saat mengambil bantuan: Membawa Surat Undangan Barcode resmi KPM, KTP Elektronik asli penerima, dan Kartu Keluarga (KK) asli.\n- Pengambilan tidak boleh diwakilkan kecuali oleh anggota keluarga yang berada dalam 1 Kartu Keluarga dengan membawa KTP asli pemohon dan perwakilan.",
                    "Alur Pendaftaran Penerima Bansos Baru (Masuk DTKS):\n1. Warga mengajukan diri ke Ketua RT atau Kepala Dusun (Kadus).\n2. Tim Poskesos Madani (Ketua: Heri Purnomo) melakukan peninjauan kondisi rumah dan aset keluarga.\n3. Dilaksanakan Musyawarah Desa (Musdes) Verifikasi DTKS bersama Kepala Pekon, BHP, Pendamping PKH, dan Tokoh Masyarakat.\n4. Data keluarga yang memenuhi kriteria diusulkan melalui operator sistem SIKS-NG ke Kementerian Sosial RI.",
                    "Panduan Mengatasi Anomali Data DTKS (Penyebab Bansos Terhenti/Tidak Cair):\n- Jika warga mendapati bansosnya tiba-tiba tidak cair, kemungkinan data kependudukan mengalami anomali di sistem pusat (perbedaan ejaan nama, perbedaan NIK, atau beda tanggal lahir antara KK, KTP, Akta Kelahiran, dan Buku Nikah).\n- Solusi: Segera datang ke Sekretariat Poskesos Madani di Balai Pekon Margodadi untuk mengecek status data di aplikasi SIKS-NG, lalu lakukan konsolidasi data kependudukan ke Kantor Disdukcapil Kabupaten Pringsewu.",
                ],
            ],

            // 9. SOP Layanan Administrasi Publik
            [
                'title' => 'Standar Pelayanan dan Prosedur Persuratan Administrasi Pekon Margodadi',
                'domain' => ServiceDomain::PUBLIC_SERVICE,
                'source' => 'Kantor Pemerintah Pekon Margodadi',
                'validator' => 'Kasi Pemerintahan (Didi Gunari)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Prinsip Pelayanan Publik Pekon Margodadi: Pelayanan administrasi dibuka setiap hari kerja Senin sampai Jumat pukul 08.00 - 15.00 WIB di Kantor Balai Pekon Margodadi. Seluruh biaya administrasi persuratan di Pekon Margodadi adalah GRATIS (Rp 0), bebas pungutan liar.",
                    "Persyaratan Dokumen Administrasi Umum di Pekon Margodadi:\n1. Surat Keterangan Usaha (SKU): Pengantar RT, KTP asli/fotokopi, KK, dan foto tempat usaha.\n2. Surat Keterangan Tidak Mampu (SKTM): Pengantar RT/RW, fotokopi KTP & KK, terdaftar basis data DTKS/P3KE, serta foto rumah.\n3. Pengantar KTP / KK: Pengantar RT, KK lama, Akta Kelahiran/Ijazah, atau Surat Kehilangan jika hilang.\n4. Surat Domisili: Pengantar RT/Kadus, KTP & KK pemohon.\n5. Surat Keterangan Kematian: Pengantar RT, surat kematian RS/Bidan (jika ada), KTP & KK almarhum, serta KTP pelapor dan 2 saksi.\n6. Pengantar Nikah (N1-N4): Pengantar RT, KTP & KK calon pengantin dan orang tua, pas foto 2x3 dan 4x6 latar biru, surat imunisasi TT Puskesmas, diajukan minimal 15 hari sebelum pernikahan.",
                ],
            ],

            // 10. Direktori Potensi dan Produk Unggulan UMKM
            [
                'title' => 'Direktori Potensi dan Produk Unggulan UMKM Pekon Margodadi',
                'domain' => ServiceDomain::UMKM,
                'source' => 'Data_UMKM_Margodadi_AI.xlsx',
                'validator' => 'Kaur Perencanaan (Fitri Anggeraeni)',
                'version' => 'v2026.1',
                'chunks' => [
                    "Daftar Produk Unggulan UMKM dan Komoditas Utama Pekon Margodadi:\nPekon Margodadi memiliki berbagai produk unggulan dan potensi ekonomi binaan warga di 4 dusun, meliputi:\n1. Sektor Pertanian & Olahan Pangan: Beras kemasan dan penggilingan padi (Pabrik Padi Iratno, Pabrik Padi Yudi, Agen Beras Anwar, Agen Beras & Pabrik Padi Aji Idar).\n2. Sektor Bahan Bangunan & Manufaktur: Sentra industri batu bata merah berkualitas dan pembuatan genteng tradisional (Batu Bata Mayoritas, Usaha Pembuatan Genteng, Usaha Batu Bata Sumberdadi, Pabrik Genteng Margorejo).\n3. Sektor Peternakan Rakyat: Telur ayam ras segar, telur asin, peternakan ayam broiler/pedaging, peternakan kambing dan sapi potong (Telur Ayam Tasya, Roys Boiler dan Ras, Usaha Telur Asin & Keripik Pisang Bu Endang, Rian Farm, Margodadi Farm, Gabriel Farm).\n4. Sektor Konveksi & Kriya Kayu: Pembuatan pakaian jahit/konveksi baju (Konveksi Baju 1 & 2), pengolahan kayu bangunan dan kusen aluminium (Panglong Kayu, Beni Aluminium, Sugi Panglong), serta kerajinan seni kreatif (Jaya Art).\n5. Sektor Kuliner Desa: Keripik pisang aneka rasa, bakpau hijau, olahan ayam goreng (Usaha Chicken), warung sate, dan aneka olahan pangan siap saji.",
                    "Sebaran Potensi 71 Pelaku UMKM Pekon Margodadi per Wilayah Dusun:\n- Dusun Margorejo (25 UMKM): Pusat peternakan kambing/sapi, pabrik genteng & batu bata, agen beras, pasar pagi desa, bengkel otomotif, salon, kuliner olahan, dan toko sembako.\n- Dusun Sumberdadi (22 UMKM): Industri batu bata merah, konveksi pembuatan pakaian, penggilingan padi, pengolahan mebel/kusen aluminium, pakan ternak, dan bengkel las teknik.\n- Dusun Margodadi (17 UMKM): Agen beras dan pangan pekon, toko bahan bangunan, apotek/layanan kesehatan, peternakan telur ayam, kuliner bakso & warung makan, bengkel mobil/motor, dan pangkas rambut.\n- Dusun Bulurejo (7 UMKM): Peternakan ayam petelur & sapi, industri batu bata Bulurejo, toko kelontong warga, dan usaha daur ulang.",
                    "Cara Pembelian, Pemesanan, dan Kemitraan UMKM Margodadi:\nPembeli, wisatawan, dan calon mitra dapat langsung mendatangi gerai produksi di dusun terkait atau menghubungi kontak WhatsApp pelaku usaha melalui Katalog Direktori UMKM Pekon Margodadi. Seluruh unit usaha terdaftar dan dibina oleh Pemerintah Pekon Margodadi untuk memperkuat ketahanan ekonomi perdesaan.",
                ],
            ],
        ];

        foreach ($documents as $docData) {
            $chunks = $docData['chunks'];
            unset($docData['chunks']);

            $doc = KbDocument::updateOrCreate(
                ['title' => $docData['title']],
                $docData
            );

            // Bersihkan chunk lama jika ada
            $doc->chunks()->delete();

            foreach ($chunks as $index => $chunkText) {
                KbChunk::create([
                    'document_id' => $doc->document_id,
                    'chunk_index' => $index,
                    'content' => $chunkText,
                    'metadata' => [
                        'domain' => $doc->domain?->value,
                        'title' => $doc->title,
                        'source' => $doc->source,
                        'validator' => $doc->validator,
                        'version' => $doc->version,
                        'is_active' => true,
                    ],
                    'created_at' => now(),
                ]);
            }
        }
    }
}

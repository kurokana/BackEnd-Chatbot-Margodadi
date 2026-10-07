<?php

namespace Database\Seeders;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\HitlEventType;
use App\Enums\PriorityLevel;
use App\Enums\SenderType;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\HitlEvent;
use App\Models\Message;
use App\Models\Operator;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Operator::where('email', 'admin@margodadi.desa.id')->first();
        $siti = Operator::where('email', 'siti.aminah@margodadi.desa.id')->first();
        $ahmad = Operator::where('email', 'ahmad.fauzi@margodadi.desa.id')->first();

        $catAdmin = ServiceCategory::where('slug', 'layanan-kependudukan')->first();
        $catUmkm = ServiceCategory::where('slug', 'potensi-umkm')->first();
        $catSampah = ServiceCategory::where('slug', 'edukasi-sampah')->first();
        $catInfo = ServiceCategory::where('slug', 'informasi-publik')->first();

        // 1. User Bpk. Joko (WhatsApp)
        $userJoko = User::firstOrCreate(
            ['phone_number' => '081234567801'],
            ['anonymous_code' => 'ANON-WA-001']
        );

        $conv1 = Conversation::updateOrCreate(
            ['external_conversation_id' => 'CV-00123'],
            [
                'user_id' => $userJoko->user_id,
                'category_id' => $catAdmin?->category_id,
                'assigned_operator_id' => $siti?->operator_id,
                'channel' => ChannelType::WHATSAPP,
                'status' => ConversationStatus::ASSIGNED,
                'priority' => PriorityLevel::HIGH,
                'needs_human' => true,
                'citizen_name' => 'Warga #A001 (Bpk. Joko)',
                'started_at' => now()->subMinutes(25),
                'last_message_at' => now()->subMinutes(5),
            ]
        );

        Message::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'content' => 'Halo selamat pagi, mau tanya syarat bikin Surat Keterangan Usaha (SKU) untuk pengajuan KUR BRI apa saja ya?'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(25)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'content' => 'Selamat pagi! Berdasarkan SOP Pelayanan Administrasi Pekon Margodadi, syarat pengurusan SKU meliputi: 1. Pengantar dari RT/RW setempat, 2. Fotokopi KTP & KK Pemohon, 3. Foto lokasi usaha / jenis usaha. Jam pelayanan kantor pekon adalah Senin-Jumat pukul 08.00 - 15.00 WIB.'],
            ['sender_type' => SenderType::BOT, 'created_at' => now()->subMinutes(24)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'content' => 'Kalau KTP saya masih alamat pekon sebelah tapi usaha sudah jalan 3 tahun di Dusun 2 Margodadi apakah tetap bisa diterbitkan suratnya?'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(20)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'content' => 'Pertanyaan ini memerlukan verifikasi kebijakan domisili usaha khusus. Mengalihkan percakapan ke operator aparatur pekon...'],
            ['sender_type' => SenderType::SYSTEM, 'metadata' => ['is_alert' => true], 'created_at' => now()->subMinutes(19)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'content' => 'Selamat pagi Pak Joko, saya Siti dari Seksi Pelayanan Pekon Margodadi. Untuk kasus KTP luar domisili, Bapak perlu melampirkan Surat Keterangan Domisili Tempat Tinggal sementara dan Surat Perjanjian Sewa/Keterangan Tempat Usaha dari pemilik tanah dusun 2.'],
            ['sender_type' => SenderType::OPERATOR, 'operator_id' => $siti?->operator_id, 'created_at' => now()->subMinutes(12)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'content' => 'Baik bu, berkas surat pengantar RT sudah saya scan.'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(5)]
        );

        HitlEvent::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'event_type' => HitlEventType::NEED_HUMAN],
            ['notes' => 'AI mendeteksi pertanyaan domisili khusus non-katalog', 'created_at' => now()->subMinutes(19)]
        );
        HitlEvent::firstOrCreate(
            ['conversation_id' => $conv1->conversation_id, 'event_type' => HitlEventType::OPERATOR_ASSIGNED],
            ['operator_id' => $siti?->operator_id, 'notes' => 'Ditugaskan kepada Operator Siti Aminah', 'created_at' => now()->subMinutes(15)]
        );

        // 2. User Ibu Ratna (Web Portal) - URGENT NEED HUMAN
        $userRatna = User::firstOrCreate(
            ['phone_number' => '081234567802'],
            ['anonymous_code' => 'ANON-WEB-092']
        );

        $conv2 = Conversation::updateOrCreate(
            ['external_conversation_id' => 'CV-00124'],
            [
                'user_id' => $userRatna->user_id,
                'category_id' => $catSampah?->category_id,
                'assigned_operator_id' => null,
                'channel' => ChannelType::WEB,
                'status' => ConversationStatus::OPEN,
                'priority' => PriorityLevel::URGENT,
                'needs_human' => true,
                'citizen_name' => 'Warga #W092 (Ibu Ratna)',
                'started_at' => now()->subMinutes(15),
                'last_message_at' => now()->subMinutes(2),
            ]
        );

        Message::firstOrCreate(
            ['conversation_id' => $conv2->conversation_id, 'content' => 'Halo, saya mau ikut program Bank Sampah Berkah Margodadi.'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(15)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv2->conversation_id, 'content' => 'Halo Ibu Ratna! Program Bank Sampah Berkah melayani penimbangan setiap hari Minggu ke-2 dan ke-4 di Balai Dusun 1. Kategori yang diterima meliputi kardus, botol plastik PET, kaleng, dan buku bekas.'],
            ['sender_type' => SenderType::BOT, 'created_at' => now()->subMinutes(14)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv2->conversation_id, 'content' => 'Apakah minyak jelantah bekas gorengan bisa disetor ke Bank Sampah Berkah? Saya ada 5 jerigen.'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(2)]
        );

        HitlEvent::firstOrCreate(
            ['conversation_id' => $conv2->conversation_id, 'event_type' => HitlEventType::NEED_HUMAN],
            ['notes' => 'User menanyakan komoditas jelantah non-katalog', 'created_at' => now()->subMinutes(2)]
        );

        // 3. User Pak Hendra (WhatsApp) - Assigned to Ahmad
        $userHendra = User::firstOrCreate(
            ['phone_number' => '081234567803'],
            ['anonymous_code' => 'ANON-WA-015']
        );

        $conv3 = Conversation::updateOrCreate(
            ['external_conversation_id' => 'CV-00125'],
            [
                'user_id' => $userHendra->user_id,
                'category_id' => $catUmkm?->category_id,
                'assigned_operator_id' => $ahmad?->operator_id,
                'channel' => ChannelType::WHATSAPP,
                'status' => ConversationStatus::ASSIGNED,
                'priority' => PriorityLevel::MEDIUM,
                'needs_human' => false,
                'citizen_name' => 'Warga #A015 (Pak Hendra)',
                'started_at' => now()->subMinutes(60),
                'last_message_at' => now()->subMinutes(30),
            ]
        );

        Message::firstOrCreate(
            ['conversation_id' => $conv3->conversation_id, 'content' => 'Bagaimana cara mendaftarkan produk keripik pisang saya ke etalase website UMKM Desa Margodadi?'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(60)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv3->conversation_id, 'content' => 'Selamat pagi Pak Hendra. Pendaftaran bisa langsung mengisi formulir online di menu Potensi UMKM atau membawa sampel produk & foto ke kantor pekon setiap jam kerja.'],
            ['sender_type' => SenderType::OPERATOR, 'operator_id' => $ahmad?->operator_id, 'created_at' => now()->subMinutes(45)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv3->conversation_id, 'content' => 'Terima kasih Mas Ahmad infonya sangat jelas.'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subMinutes(30)]
        );

        // 4. User Anonim (Web) - Resolved
        $userAnon = User::firstOrCreate(
            ['anonymous_code' => 'ANON-WEB-088'],
            ['phone_number' => null]
        );

        $conv4 = Conversation::updateOrCreate(
            ['external_conversation_id' => 'CV-00122'],
            [
                'user_id' => $userAnon->user_id,
                'category_id' => $catInfo?->category_id,
                'assigned_operator_id' => $siti?->operator_id,
                'channel' => ChannelType::WEB,
                'status' => ConversationStatus::RESOLVED,
                'priority' => PriorityLevel::LOW,
                'needs_human' => false,
                'citizen_name' => 'Warga #W088 (Anonim)',
                'started_at' => now()->subHours(3),
                'last_message_at' => now()->subHours(2),
                'resolved_at' => now()->subHours(2),
            ]
        );

        Message::firstOrCreate(
            ['conversation_id' => $conv4->conversation_id, 'content' => 'Jadwal pelayanan posyandu balita dusun 3 tanggal berapa ya?'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subHours(3)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv4->conversation_id, 'content' => 'Jadwal Posyandu Melati Dusun 3 diadakan rutin setiap tanggal 18 pukul 08.30 WIB di Balai Posyandu Dusun 3.'],
            ['sender_type' => SenderType::BOT, 'created_at' => now()->subHours(3)]
        );
        Message::firstOrCreate(
            ['conversation_id' => $conv4->conversation_id, 'content' => 'Terima kasih informasinya, sudah terjawab lengkap.'],
            ['sender_type' => SenderType::USER, 'created_at' => now()->subHours(2)]
        );

        // Activity Logs
        ActivityLog::firstOrCreate(
            ['target' => '#CV-'.$conv1->conversation_id, 'action' => 'OPERATOR_RESPONSE'],
            [
                'operator_id' => $siti?->operator_id,
                'actor_name' => $siti?->name ?? 'Siti Aminah',
                'description' => 'Operator membalas panduan domisili usaha SKU warga',
                'channel' => ChannelType::WHATSAPP,
                'created_at' => now()->subMinutes(12),
            ]
        );
        ActivityLog::firstOrCreate(
            ['target' => '#CV-'.$conv3->conversation_id, 'action' => 'OPERATOR_RESPONSE'],
            [
                'operator_id' => $ahmad?->operator_id,
                'actor_name' => $ahmad?->name ?? 'Ahmad Fauzi',
                'description' => 'Operator memberikan panduan kurasi produk UMKM',
                'channel' => ChannelType::WHATSAPP,
                'created_at' => now()->subMinutes(45),
            ]
        );
        ActivityLog::firstOrCreate(
            ['target' => '#OP-'.$siti?->operator_id, 'action' => 'OPERATOR_STATUS'],
            [
                'operator_id' => $siti?->operator_id,
                'actor_name' => $siti?->name ?? 'Siti Aminah',
                'description' => 'Operator masuk ke antrean layanan (Status: ONLINE)',
                'channel' => ChannelType::WEB,
                'created_at' => now()->subHours(4),
            ]
        );
    }
}

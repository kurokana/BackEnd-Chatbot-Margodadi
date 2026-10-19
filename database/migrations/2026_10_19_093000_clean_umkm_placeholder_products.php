<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update Putra Mandiri
        DB::table('umkm_products')
            ->whereIn('umkm_id', function ($query) {
                $query->select('umkm_id')->from('umkms')->where('name', 'Putra Mandiri');
            })
            ->where('name', 'like', '%belum diketahui%')
            ->update([
                'name' => 'Layanan perdagangan dan jasa',
                'description' => 'Layanan perdagangan dan jasa umum dari Putra Mandiri di Dusun Sumberdadi.',
                'updated_at' => now(),
            ]);

        // 2. Clean Jaya Art
        $jayaArt = DB::table('umkms')->where('name', 'Jaya Art')->first();
        if ($jayaArt) {
            DB::table('umkm_products')
                ->where('umkm_id', $jayaArt->umkm_id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%belum diketahui%')
                      ->orWhere('name', 'like', '%usaha patungan%');
                })
                ->delete();

            $exists = DB::table('umkm_products')
                ->where('umkm_id', $jayaArt->umkm_id)
                ->where('name', 'Kerajinan seni dan kriya tangan')
                ->exists();

            if (! $exists) {
                DB::table('umkm_products')->insert([
                    'umkm_id' => $jayaArt->umkm_id,
                    'name' => 'Kerajinan seni dan kriya tangan',
                    'description' => 'Produk kerajinan dan karya seni kreatif dari Jaya Art di Dusun Sumberdadi.',
                    'price' => 'Hubungi Penjual',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('umkms')
                ->where('umkm_id', $jayaArt->umkm_id)
                ->update([
                    'sub_title' => 'Kerajinan seni dan kriya tangan',
                    'description' => 'Usaha Jaya Art bergerak di bidang Kreatif dan kerajinan. Produk atau layanan: Kerajinan seni dan kriya tangan. Berlokasi di Dusun Sumberdadi, Pekon Margodadi.',
                    'updated_at' => now(),
                ]);
        }

        // 3. Update description of all umkm_products to say 'Produk atau layanan' instead of 'Produk / layanan unggulan'
        DB::table('umkm_products')
            ->where('description', 'like', 'Produk / layanan unggulan%')
            ->update([
                'description' => DB::raw("REPLACE(description, 'Produk / layanan unggulan', 'Produk atau layanan')"),
                'updated_at' => now(),
            ]);

        // 4. Clean old duplicate KB document (title 'Direktori Potensi UMKM dan Ekonomi Kreatif 4 Dusun Pekon Margodadi') if exists
        $oldDoc = DB::table('kb_documents')
            ->where('title', 'Direktori Potensi UMKM dan Ekonomi Kreatif 4 Dusun Pekon Margodadi')
            ->first();

        if ($oldDoc) {
            DB::table('kb_chunks')->where('document_id', $oldDoc->document_id)->delete();
            DB::table('kb_documents')->where('document_id', $oldDoc->document_id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};

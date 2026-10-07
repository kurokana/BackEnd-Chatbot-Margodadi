<?php

namespace Tests\Feature;

use App\Enums\ServiceDomain;
use App\Models\PublicService;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_across_public_services_and_umkms(): void
    {
        $catPublik = ServiceCategory::factory()->create([
            'name' => 'Layanan Desa',
            'domain' => ServiceDomain::PUBLIC_SERVICE,
        ]);

        $catUmkm = ServiceCategory::factory()->create([
            'name' => 'Kuliner Desa',
            'domain' => ServiceDomain::UMKM,
        ]);

        // Public service matching "pisang"
        PublicService::factory()->create([
            'category_id' => $catPublik->category_id,
            'title' => 'Izin Budidaya Pisang',
            'description' => 'Surat rekomendasi pertanian bibit pisang',
            'is_active' => true,
        ]);

        // UMKM matching "pisang"
        Umkm::factory()->create([
            'category_id' => $catUmkm->category_id,
            'name' => 'Keripik Pisang Margodadi',
            'description' => 'Produksi keripik renyah rasa coklat',
            'group_name' => 'KWT Melati',
            'is_active' => true,
        ]);

        // Unrelated UMKM
        Umkm::factory()->create([
            'category_id' => $catUmkm->category_id,
            'name' => 'Bengkel Las Maju',
            'description' => 'Jasa las pagar besi',
            'group_name' => 'Paguyuban Las',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/search?q=pisang');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'query',
                'total_results',
                'data' => [
                    'public_services',
                    'umkms',
                ],
                'counts' => [
                    'public_services',
                    'umkms',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'query' => 'pisang',
                'total_results' => 2,
                'counts' => [
                    'public_services' => 1,
                    'umkms' => 1,
                ],
            ])
            ->assertJsonPath('data.public_services.0.title', 'Izin Budidaya Pisang')
            ->assertJsonPath('data.umkms.0.name', 'Keripik Pisang Margodadi');
    }

    public function test_can_filter_search_by_type(): void
    {
        $catPublik = ServiceCategory::factory()->create(['domain' => ServiceDomain::PUBLIC_SERVICE]);
        $catUmkm = ServiceCategory::factory()->create(['domain' => ServiceDomain::UMKM]);

        PublicService::factory()->create([
            'category_id' => $catPublik->category_id,
            'title' => 'Layanan Kependudukan Margodadi',
            'is_active' => true,
        ]);

        Umkm::factory()->create([
            'category_id' => $catUmkm->category_id,
            'name' => 'Kopi Margodadi Asli',
            'is_active' => true,
        ]);

        // Search only public services
        $resPublik = $this->getJson('/api/search?q=Margodadi&type=public_services');
        $resPublik->assertStatus(200)
            ->assertJson([
                'total_results' => 1,
                'counts' => [
                    'public_services' => 1,
                    'umkms' => 0,
                ],
            ])
            ->assertJsonCount(1, 'data.public_services')
            ->assertJsonCount(0, 'data.umkms');

        // Search only UMKMs
        $resUmkm = $this->getJson('/api/search?q=Margodadi&type=umkms');
        $resUmkm->assertStatus(200)
            ->assertJson([
                'total_results' => 1,
                'counts' => [
                    'public_services' => 0,
                    'umkms' => 1,
                ],
            ])
            ->assertJsonCount(0, 'data.public_services')
            ->assertJsonCount(1, 'data.umkms');
    }

    public function test_empty_search_query_returns_gracefully(): void
    {
        $response = $this->getJson('/api/search?q=');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'query' => '',
                'total_results' => 0,
                'data' => [
                    'public_services' => [],
                    'umkms' => [],
                ],
                'counts' => [
                    'public_services' => 0,
                    'umkms' => 0,
                ],
            ]);
    }

    public function test_inactive_items_are_excluded_from_global_search(): void
    {
        $cat = ServiceCategory::factory()->create();

        PublicService::factory()->create([
            'category_id' => $cat->category_id,
            'title' => 'Layanan Rahasia',
            'is_active' => false,
        ]);

        Umkm::factory()->create([
            'category_id' => $cat->category_id,
            'name' => 'UMKM Tutup Rahasia',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/search?q=Rahasia');

        $response->assertStatus(200)
            ->assertJson([
                'total_results' => 0,
            ]);
    }
}

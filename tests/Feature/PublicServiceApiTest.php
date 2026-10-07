<?php

namespace Tests\Feature;

use App\Enums\ServiceDomain;
use App\Models\PublicService;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_public_services_with_pagination(): void
    {
        $category = ServiceCategory::factory()->create([
            'name' => 'Kependudukan',
            'slug' => 'kependudukan',
            'domain' => ServiceDomain::PUBLIC_SERVICE,
        ]);

        PublicService::factory()->count(15)->create([
            'category_id' => $category->category_id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public-services?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'service_id',
                        'title',
                        'slug',
                        'category_badge',
                        'sub_category_badge',
                        'description',
                        'sla_duration',
                        'cost_info',
                        'officer_in_charge',
                        'download_url',
                        'tags',
                        'category',
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'has_more_pages',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 15,
                    'per_page' => 10,
                    'last_page' => 2,
                    'has_more_pages' => true,
                ],
            ]);
    }

    public function test_can_search_public_services_by_keyword(): void
    {
        $category = ServiceCategory::factory()->create();

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Pembuatan Surat Keterangan Usaha (SKU)',
            'description' => 'Surat pengantar bank',
            'tags' => ['sku', 'usaha', 'bank'],
            'is_active' => true,
        ]);

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Surat Keterangan Kematian',
            'description' => 'Pencatatan akta kematian',
            'tags' => ['kematian', 'akta'],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public-services?search=SKU');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 1,
                ],
            ])
            ->assertJsonPath('data.0.title', 'Pembuatan Surat Keterangan Usaha (SKU)');
    }

    public function test_can_filter_public_services_by_category_slug(): void
    {
        $cat1 = ServiceCategory::factory()->create(['slug' => 'usaha-desa']);
        $cat2 = ServiceCategory::factory()->create(['slug' => 'sosial-desa']);

        PublicService::factory()->create([
            'category_id' => $cat1->category_id,
            'title' => 'Layanan Usaha 1',
            'is_active' => true,
        ]);

        PublicService::factory()->create([
            'category_id' => $cat2->category_id,
            'title' => 'Layanan Sosial 1',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public-services?category=usaha-desa');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 1,
                ],
            ])
            ->assertJsonPath('data.0.title', 'Layanan Usaha 1');
    }

    public function test_can_filter_public_services_by_badge(): void
    {
        $category = ServiceCategory::factory()->create();

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Surat Pengantar Nikah',
            'category_badge' => 'SURAT_PENGANTAR',
            'is_active' => true,
        ]);

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Izin Sewa Gedung Balai',
            'category_badge' => 'FASILITAS',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public-services?badge=SURAT_PENGANTAR');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 1,
                ],
            ])
            ->assertJsonPath('data.0.title', 'Surat Pengantar Nikah');
    }

    public function test_inactive_public_services_are_excluded(): void
    {
        $category = ServiceCategory::factory()->create();

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Layanan Aktif',
            'is_active' => true,
        ]);

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Layanan Nonaktif',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/public-services');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 1,
                ],
            ])
            ->assertJsonPath('data.0.title', 'Layanan Aktif');
    }

    public function test_can_get_public_service_detail_by_slug(): void
    {
        $category = ServiceCategory::factory()->create(['name' => 'Layanan Umum']);

        $service = PublicService::factory()->create([
            'category_id' => $category->category_id,
            'title' => 'Surat Keterangan Usaha',
            'slug' => 'surat-keterangan-usaha',
            'requirements' => ['FC KTP', 'FC KK'],
            'steps' => [['step' => 1, 'name' => 'Loket Pelayanan']],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public-services/surat-keterangan-usaha');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'service_id',
                    'title',
                    'slug',
                    'requirements',
                    'steps',
                    'category',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'slug' => 'surat-keterangan-usaha',
                    'requirements' => ['FC KTP', 'FC KK'],
                ],
            ]);
    }

    public function test_returns_404_for_non_existent_or_inactive_service(): void
    {
        $category = ServiceCategory::factory()->create();

        PublicService::factory()->create([
            'category_id' => $category->category_id,
            'slug' => 'layanan-tutup',
            'is_active' => false,
        ]);

        $response1 = $this->getJson('/api/public-services/layanan-tidak-ada');
        $response1->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);

        $response2 = $this->getJson('/api/public-services/layanan-tutup');
        $response2->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_can_list_public_service_categories_with_count(): void
    {
        $cat1 = ServiceCategory::factory()->create([
            'name' => 'Administrasi Pekon',
            'slug' => 'administrasi-pekon',
            'domain' => ServiceDomain::PUBLIC_SERVICE,
            'is_active' => true,
        ]);

        $cat2 = ServiceCategory::factory()->create([
            'name' => 'UMKM Katalog',
            'slug' => 'umkm-katalog',
            'domain' => ServiceDomain::UMKM,
            'is_active' => true,
        ]);

        PublicService::factory()->count(3)->create([
            'category_id' => $cat1->category_id,
            'is_active' => true,
        ]);

        PublicService::factory()->create([
            'category_id' => $cat1->category_id,
            'is_active' => false, // inactive should not be counted
        ]);

        $response = $this->getJson('/api/public-services/categories');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonCount(1, 'data') // only PUBLIC_SERVICE domain
            ->assertJsonPath('data.0.slug', 'administrasi-pekon')
            ->assertJsonPath('data.0.public_services_count', 3);
    }
}

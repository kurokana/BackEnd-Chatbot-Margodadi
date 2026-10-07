<?php

namespace Tests\Feature;

use App\Enums\ServiceDomain;
use App\Models\ServiceCategory;
use App\Models\Umkm;
use App\Models\UmkmProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_umkms_with_pagination(): void
    {
        $category = ServiceCategory::factory()->create([
            'domain' => ServiceDomain::UMKM,
        ]);

        $umkms = Umkm::factory()->count(12)->create([
            'category_id' => $category->category_id,
            'is_active' => true,
        ]);

        UmkmProduct::factory()->count(2)->create([
            'umkm_id' => $umkms[0]->umkm_id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/umkms?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'umkm_id',
                        'reg_number',
                        'name',
                        'sub_title',
                        'owner_name',
                        'phone',
                        'wa_number',
                        'address',
                        'description',
                        'banner_image_url',
                        'legal_certification',
                        'group_name',
                        'category',
                        'products_count',
                        'last_verified_at',
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
                    'total' => 12,
                    'per_page' => 5,
                    'last_page' => 3,
                    'has_more_pages' => true,
                ],
            ]);
    }

    public function test_can_search_umkms_by_keyword(): void
    {
        $category = ServiceCategory::factory()->create(['domain' => ServiceDomain::UMKM]);

        Umkm::factory()->create([
            'category_id' => $category->category_id,
            'name' => 'Keripik Pisang Margodadi Berkibar',
            'owner_name' => 'Ibu Siti',
            'description' => 'Keripik pisang kepok aneka rasa',
            'is_active' => true,
        ]);

        Umkm::factory()->create([
            'category_id' => $category->category_id,
            'name' => 'Anyaman Bambu Tradisional',
            'owner_name' => 'Pak Joko',
            'description' => 'Kerajinan besek bambu',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/umkms?search=pisang');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 1,
                ],
            ])
            ->assertJsonPath('data.0.name', 'Keripik Pisang Margodadi Berkibar');
    }

    public function test_can_filter_umkms_by_category_slug(): void
    {
        $catKuliner = ServiceCategory::factory()->create([
            'name' => 'Kuliner Pangan',
            'slug' => 'kuliner-pangan',
            'domain' => ServiceDomain::UMKM,
        ]);

        $catKerajinan = ServiceCategory::factory()->create([
            'name' => 'Kerajinan Tangan',
            'slug' => 'kerajinan-tangan',
            'domain' => ServiceDomain::UMKM,
        ]);

        Umkm::factory()->create([
            'category_id' => $catKuliner->category_id,
            'name' => 'Kopi Robusta Margodadi',
            'is_active' => true,
        ]);

        Umkm::factory()->create([
            'category_id' => $catKerajinan->category_id,
            'name' => 'Tas Rajut Cantik',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/umkms?category=kuliner-pangan');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pagination' => [
                    'total' => 1,
                ],
            ])
            ->assertJsonPath('data.0.name', 'Kopi Robusta Margodadi');
    }

    public function test_can_sort_umkms_alphabetically_and_by_date(): void
    {
        $category = ServiceCategory::factory()->create(['domain' => ServiceDomain::UMKM]);

        $u1 = Umkm::factory()->create([
            'category_id' => $category->category_id,
            'name' => 'Cilok Crispy Margodadi',
            'is_active' => true,
        ]);

        $u2 = Umkm::factory()->create([
            'category_id' => $category->category_id,
            'name' => 'Abon Lele Sedap',
            'is_active' => true,
        ]);

        $u3 = Umkm::factory()->create([
            'category_id' => $category->category_id,
            'name' => 'Bolu Ketan Hitam',
            'is_active' => true,
        ]);

        // Test Sort A-Z
        $resAsc = $this->getJson('/api/umkms?sort=a-z');
        $resAsc->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Abon Lele Sedap')
            ->assertJsonPath('data.1.name', 'Bolu Ketan Hitam')
            ->assertJsonPath('data.2.name', 'Cilok Crispy Margodadi');

        // Test Sort Z-A
        $resDesc = $this->getJson('/api/umkms?sort=z-a');
        $resDesc->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Cilok Crispy Margodadi')
            ->assertJsonPath('data.1.name', 'Bolu Ketan Hitam')
            ->assertJsonPath('data.2.name', 'Abon Lele Sedap');

        // Test Sort Terlama (Oldest)
        $resOldest = $this->getJson('/api/umkms?sort=terlama');
        $resOldest->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Cilok Crispy Margodadi');

        // Test Sort Terbaru (Newest)
        $resNewest = $this->getJson('/api/umkms?sort=terbaru');
        $resNewest->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Bolu Ketan Hitam');
    }

    public function test_can_get_umkm_detail_by_id_or_reg_number(): void
    {
        $category = ServiceCategory::factory()->create(['domain' => ServiceDomain::UMKM]);

        $umkm = Umkm::factory()->create([
            'category_id' => $category->category_id,
            'reg_number' => 'MKD-UMKM-777',
            'name' => 'Gula Semut Aren Margodadi',
            'history' => 'Warisan keluarga sejak 1980',
            'is_active' => true,
        ]);

        $activeProduct = UmkmProduct::factory()->create([
            'umkm_id' => $umkm->umkm_id,
            'name' => 'Gula Semut 500g',
            'is_active' => true,
        ]);

        $inactiveProduct = UmkmProduct::factory()->create([
            'umkm_id' => $umkm->umkm_id,
            'name' => 'Produk Habis',
            'is_active' => false,
        ]);

        // Get by ID
        $responseById = $this->getJson('/api/umkms/' . $umkm->umkm_id);
        $responseById->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'umkm_id' => $umkm->umkm_id,
                    'name' => 'Gula Semut Aren Margodadi',
                    'history' => 'Warisan keluarga sejak 1980',
                ],
            ])
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Gula Semut 500g');

        // Get by Reg Number
        $responseByReg = $this->getJson('/api/umkms/MKD-UMKM-777');
        $responseByReg->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'reg_number' => 'MKD-UMKM-777',
                    'name' => 'Gula Semut Aren Margodadi',
                ],
            ]);
    }

    public function test_returns_404_for_non_existent_or_inactive_umkm(): void
    {
        $category = ServiceCategory::factory()->create(['domain' => ServiceDomain::UMKM]);

        $inactiveUmkm = Umkm::factory()->create([
            'category_id' => $category->category_id,
            'is_active' => false,
        ]);

        $res1 = $this->getJson('/api/umkms/99999');
        $res1->assertStatus(404)->assertJson(['status' => 'error']);

        $res2 = $this->getJson('/api/umkms/' . $inactiveUmkm->umkm_id);
        $res2->assertStatus(404)->assertJson(['status' => 'error']);
    }

    public function test_can_list_umkm_categories_with_active_count(): void
    {
        $cat1 = ServiceCategory::factory()->create([
            'name' => 'Kuliner Margodadi',
            'domain' => ServiceDomain::UMKM,
            'is_active' => true,
        ]);

        $catPublik = ServiceCategory::factory()->create([
            'name' => 'Kependudukan',
            'domain' => ServiceDomain::PUBLIC_SERVICE,
            'is_active' => true,
        ]);

        Umkm::factory()->count(2)->create([
            'category_id' => $cat1->category_id,
            'is_active' => true,
        ]);

        Umkm::factory()->create([
            'category_id' => $cat1->category_id,
            'is_active' => false, // should not be counted
        ]);

        $response = $this->getJson('/api/umkms/categories');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonCount(1, 'data') // only UMKM domain
            ->assertJsonPath('data.0.name', 'Kuliner Margodadi')
            ->assertJsonPath('data.0.public_services_count', null)
            ->assertJsonPath('data.0.category_id', $cat1->category_id);
    }
}

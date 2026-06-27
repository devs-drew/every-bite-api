<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_search_returns_matching_products(): void
    {
        Product::create([
            'code' => '4800012345678',
            'product_name' => 'Lucky Me Pancit Canton',
            'brand_name' => 'Monde Nissin',
            'serving_size_g' => 65,
            'calories' => 440,
            'protein_g' => 10.0,
            'carbs_g' => 62.0,
            'fat_g' => 15.0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/products/search?q=lucky');

        $response->assertOk()
            ->assertJsonStructure(['products', 'failed'])
            ->assertJsonPath('failed', false)
            ->assertJsonPath('products.0.food_name', 'Lucky Me Pancit Canton')
            ->assertJsonPath('products.0.off_product_id', '4800012345678')
            ->assertJsonPath('products.0.nutrients_per_100g.calories', 440);
    }

    public function test_search_includes_user_custom_foods_before_catalog(): void
    {
        Product::create([
            'code' => '111',
            'product_name' => 'Protein Bar Generic',
            'calories' => 380,
        ]);
        $this->user->customFoods()->create([
            'food_name' => 'My Protein Bar',
            'calories' => 350,
            'serving_size_g' => 60,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/products/search?q=protein');

        $products = $response->json('products');
        $this->assertStringStartsWith('custom-', $products[0]['off_product_id']);
        $this->assertEquals('My Protein Bar', $products[0]['food_name']);
    }

    public function test_search_requires_auth(): void
    {
        $this->getJson('/api/products/search?q=test')->assertUnauthorized();
    }

    public function test_barcode_returns_product_from_db(): void
    {
        Product::create([
            'code' => '4800016502090',
            'product_name' => 'Lucky Me Canton',
            'calories' => 440,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/products/barcode/4800016502090');

        $response->assertOk()
            ->assertJsonPath('food_name', 'Lucky Me Canton')
            ->assertJsonPath('barcode', '4800016502090')
            ->assertJsonPath('off_product_id', '4800016502090');
    }

    public function test_barcode_returns_user_custom_food_when_not_in_catalog(): void
    {
        $this->user->customFoods()->create([
            'food_name' => 'My Barcoded Food',
            'barcode' => '9999999999999',
            'calories' => 200,
            'serving_size_g' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/products/barcode/9999999999999');

        $response->assertOk()
            ->assertJsonPath('food_name', 'My Barcoded Food')
            ->assertJsonPath('barcode', '9999999999999');
    }

    public function test_barcode_requires_auth(): void
    {
        $this->getJson('/api/products/barcode/1234')->assertUnauthorized();
    }
}

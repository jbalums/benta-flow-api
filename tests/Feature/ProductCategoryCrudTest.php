<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_product_categories(): void
    {
        $this->authenticate();
        ProductCategory::factory()->count(2)->create();

        $response = $this->getJson('/api/product-categories');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'categories');
    }

    public function test_authenticated_user_can_create_product_category(): void
    {
        $this->authenticate();

        $payload = [
            'name' => 'Drinks',
            'description' => 'Beverages and juices',
        ];

        $response = $this->postJson('/api/product-categories', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('category.name', $payload['name'])
            ->assertJsonPath('category.description', $payload['description']);

        $this->assertDatabaseHas('product_categories', $payload);
    }

    public function test_authenticated_user_can_view_single_product_category(): void
    {
        $this->authenticate();
        $category = ProductCategory::factory()->create();

        $response = $this->getJson('/api/product-categories/' . $category->id);

        $response
            ->assertOk()
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('category.name', $category->name);
    }

    public function test_authenticated_user_can_update_product_category(): void
    {
        $this->authenticate();
        $category = ProductCategory::factory()->create();

        $payload = [
            'name' => 'Snacks',
            'description' => 'Light food items',
        ];

        $response = $this->patchJson('/api/product-categories/' . $category->id, $payload);

        $response
            ->assertOk()
            ->assertJsonPath('category.name', $payload['name'])
            ->assertJsonPath('category.description', $payload['description']);

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => $payload['name'],
            'description' => $payload['description'],
        ]);
    }

    public function test_authenticated_user_can_delete_product_category(): void
    {
        $this->authenticate();
        $category = ProductCategory::factory()->create();

        $response = $this->deleteJson('/api/product-categories/' . $category->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Product category deleted successfully.');

        $this->assertDatabaseMissing('product_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_product_category_routes_require_authentication(): void
    {
        $category = ProductCategory::factory()->create();

        $this->getJson('/api/product-categories')->assertUnauthorized();
        $this->postJson('/api/product-categories', [
            'name' => 'No Auth',
        ])->assertUnauthorized();
        $this->getJson('/api/product-categories/' . $category->id)->assertUnauthorized();
        $this->patchJson('/api/product-categories/' . $category->id, [
            'name' => 'Blocked',
        ])->assertUnauthorized();
        $this->deleteJson('/api/product-categories/' . $category->id)->assertUnauthorized();
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}

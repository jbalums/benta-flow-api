<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_products(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $category = ProductCategory::factory()->create();

        Product::factory()->count(2)->create(['store_id' => $store->id])->each(function (Product $product) use ($category) {
            $product->categories()->sync([$category->id]);
        });

        $response = $this->getJson('/api/products');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'products');
    }

    public function test_authenticated_user_can_create_product(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $categories = ProductCategory::factory()->count(2)->create();

        $payload = [
            'store_id' => $store->id,
            'name' => 'iPhone 15',
            'sku' => 'IPH15-001',
            'description' => 'Smartphone device',
            'price' => 1299.99,
            'stock_quantity' => 10,
            'category_ids' => $categories->pluck('id')->values()->all(),
        ];

        $response = $this->postJson('/api/products', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('product.name', $payload['name'])
            ->assertJsonPath('product.store_id', $store->id)
            ->assertJsonCount(2, 'product.categories');

        $this->assertDatabaseHas('products', [
            'name' => $payload['name'],
            'store_id' => $store->id,
            'sku' => $payload['sku'],
        ]);

        $product = Product::query()->where('sku', $payload['sku'])->firstOrFail();

        $this->assertEqualsCanonicalizing(
            $payload['category_ids'],
            $product->categories()->pluck('product_categories.id')->all()
        );
    }

    public function test_authenticated_user_can_view_single_product(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $product->categories()->sync([$category->id]);

        $response = $this->getJson('/api/products/' . $product->id);

        $response
            ->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.store.id', $store->id)
            ->assertJsonPath('product.categories.0.id', $category->id);
    }

    public function test_authenticated_user_can_update_product(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $oldCategory = ProductCategory::factory()->create();
        $newCategory = ProductCategory::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $product->categories()->sync([$oldCategory->id]);

        $payload = [
            'name' => 'Updated Product Name',
            'price' => 799.99,
            'stock_quantity' => 5,
            'category_ids' => [$newCategory->id],
        ];

        $response = $this->patchJson('/api/products/' . $product->id, $payload);

        $response
            ->assertOk()
            ->assertJsonPath('product.name', $payload['name'])
            ->assertJsonPath('product.price', $payload['price'])
            ->assertJsonPath('product.stock_quantity', $payload['stock_quantity'])
            ->assertJsonCount(1, 'product.categories');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $payload['name'],
            'stock_quantity' => $payload['stock_quantity'],
        ]);

        $this->assertEquals(
            [$newCategory->id],
            $product->fresh()->categories()->pluck('product_categories.id')->all()
        );
    }

    public function test_authenticated_user_can_delete_product(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $product->categories()->sync([$category->id]);

        $response = $this->deleteJson('/api/products/' . $product->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);

        $this->assertDatabaseMissing('product_category_product', [
            'product_id' => $product->id,
            'product_category_id' => $category->id,
        ]);
    }

    public function test_product_routes_require_authentication(): void
    {
        $store = Store::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);

        $this->getJson('/api/products')->assertUnauthorized();
        $this->postJson('/api/products', [
            'store_id' => $store->id,
            'name' => 'No Auth Product',
            'price' => 100,
            'stock_quantity' => 1,
            'category_ids' => [$category->id],
        ])->assertUnauthorized();
        $this->getJson('/api/products/' . $product->id)->assertUnauthorized();
        $this->patchJson('/api/products/' . $product->id, [
            'name' => 'Blocked',
        ])->assertUnauthorized();
        $this->deleteJson('/api/products/' . $product->id)->assertUnauthorized();
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}

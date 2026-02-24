<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_branches(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        Branch::factory()->count(2)->create(['store_id' => $store->id]);

        $response = $this->getJson('/api/branches');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'branches');
    }

    public function test_authenticated_user_can_create_branch(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $payload = [
            'store_id' => $store->id,
            'name' => 'Downtown Branch',
            'code' => 'DOWNTOWN-01',
            'phone' => '+1-555-1111',
            'address' => '123 Main Street',
            'city' => 'Austin',
            'state' => 'Texas',
            'country' => 'USA',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/branches', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('branch.name', $payload['name'])
            ->assertJsonPath('branch.code', $payload['code'])
            ->assertJsonPath('branch.store_id', $store->id);

        $this->assertDatabaseHas('branches', [
            'name' => $payload['name'],
            'store_id' => $store->id,
            'code' => $payload['code'],
        ]);
    }

    public function test_authenticated_user_can_view_single_branch(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create(['store_id' => $store->id]);

        $response = $this->getJson('/api/branches/' . $branch->id);

        $response
            ->assertOk()
            ->assertJsonPath('branch.id', $branch->id)
            ->assertJsonPath('branch.store.id', $store->id);
    }

    public function test_authenticated_user_can_update_branch(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create(['store_id' => $store->id]);

        $payload = [
            'name' => 'Updated Branch Name',
            'phone' => '+1-555-9999',
            'is_active' => false,
        ];

        $response = $this->patchJson('/api/branches/' . $branch->id, $payload);

        $response
            ->assertOk()
            ->assertJsonPath('branch.name', $payload['name'])
            ->assertJsonPath('branch.phone', $payload['phone'])
            ->assertJsonPath('branch.is_active', $payload['is_active']);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => $payload['name'],
            'phone' => $payload['phone'],
            'is_active' => $payload['is_active'],
        ]);
    }

    public function test_authenticated_user_can_delete_branch(): void
    {
        $user = $this->authenticate();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create(['store_id' => $store->id]);

        $response = $this->deleteJson('/api/branches/' . $branch->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Branch deleted successfully.');

        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
        ]);
    }

    public function test_branch_routes_require_authentication(): void
    {
        $branch = Branch::factory()->create();

        $this->getJson('/api/branches')->assertUnauthorized();
        $this->postJson('/api/branches', [
            'store_id' => $branch->store_id,
            'name' => 'No Auth Branch',
        ])->assertUnauthorized();
        $this->getJson('/api/branches/' . $branch->id)->assertUnauthorized();
        $this->patchJson('/api/branches/' . $branch->id, [
            'name' => 'Blocked',
        ])->assertUnauthorized();
        $this->deleteJson('/api/branches/' . $branch->id)->assertUnauthorized();
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}

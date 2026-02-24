<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_users(): void
    {
        $this->authenticate();
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'users');
    }

    public function test_authenticated_user_can_create_user(): void
    {
        $this->authenticate();

        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'ADMIN',
        ];

        $response = $this->postJson('/api/users', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', $payload['email'])
            ->assertJsonPath('user.role', $payload['role'])
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'role' => $payload['role'],
        ]);

        $createdUser = User::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue(Hash::check($payload['password'], $createdUser->password));
    }

    public function test_authenticated_user_can_view_single_user(): void
    {
        $this->authenticate();
        $targetUser = User::factory()->create();

        $response = $this->getJson('/api/users/' . $targetUser->id);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $targetUser->id)
            ->assertJsonPath('user.email', $targetUser->email);
    }

    public function test_authenticated_user_can_update_user(): void
    {
        $this->authenticate();
        $targetUser = User::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'role' => 'MANAGER',
        ];

        $response = $this->patchJson('/api/users/' . $targetUser->id, $payload);

        $response
            ->assertOk()
            ->assertJsonPath('user.name', $payload['name'])
            ->assertJsonPath('user.role', $payload['role']);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => $payload['name'],
            'role' => $payload['role'],
        ]);
    }

    public function test_authenticated_user_can_delete_user(): void
    {
        $this->authenticate();
        $targetUser = User::factory()->create();

        $response = $this->deleteJson('/api/users/' . $targetUser->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }

    public function test_user_routes_require_authentication(): void
    {
        $targetUser = User::factory()->create();

        $this->getJson('/api/users')->assertUnauthorized();
        $this->postJson('/api/users', [
            'name' => 'No Auth',
            'email' => 'no-auth@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();
        $this->getJson('/api/users/' . $targetUser->id)->assertUnauthorized();
        $this->patchJson('/api/users/' . $targetUser->id, [
            'name' => 'Blocked',
        ])->assertUnauthorized();
        $this->deleteJson('/api/users/' . $targetUser->id)->assertUnauthorized();
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}

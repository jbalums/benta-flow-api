<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_signup_with_email_and_password(): void
    {
        $payload = [
            'name' => 'Owner One',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/auth/signup', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Signup successful.')
            ->assertJsonPath('user.email', $payload['email'])
            ->assertJsonPath('user.role', 'OWNER')
            ->assertJsonPath('user.auth_provider', 'local')
            ->assertJsonPath('user.has_completed_store_setup', false);

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'role' => 'OWNER',
            'auth_provider' => 'local',
        ]);
    }

    public function test_user_can_signup_with_google_id_token(): void
    {
        config(['services.google.client_id' => 'google-client-id']);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-sub-123',
                'email' => 'google-owner@example.com',
                'name' => 'Google Owner',
                'aud' => 'google-client-id',
                'email_verified' => 'true',
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/signup/google', [
            'id_token' => 'fake-google-id-token',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Signup successful.')
            ->assertJsonPath('user.email', 'google-owner@example.com')
            ->assertJsonPath('user.auth_provider', 'google')
            ->assertJsonPath('user.has_completed_store_setup', false);

        $this->assertDatabaseHas('users', [
            'email' => 'google-owner@example.com',
            'google_id' => 'google-sub-123',
            'auth_provider' => 'google',
            'role' => 'OWNER',
        ]);
    }

    public function test_authenticated_user_can_save_store_details_after_signup(): void
    {
        $user = User::factory()->create(['role' => 'OWNER']);
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Benta Main Store',
            'business_type' => 'retail',
            'nature_of_business' => 'Electronics and gadgets retail',
            'phone' => '+1-555-111-2222',
            'city' => 'San Francisco',
            'country' => 'USA',
            'website' => 'https://example-store.com',
        ];

        $response = $this->postJson('/api/auth/store-details', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Store details saved successfully.')
            ->assertJsonPath('store.name', $payload['name'])
            ->assertJsonPath('store.business_type', $payload['business_type'])
            ->assertJsonPath('user.has_completed_store_setup', true);

        $this->assertDatabaseHas('stores', [
            'user_id' => $user->id,
            'name' => $payload['name'],
            'business_type' => $payload['business_type'],
            'nature_of_business' => $payload['nature_of_business'],
        ]);
    }

    public function test_store_details_route_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/store-details', [
            'name' => 'No Auth Store',
            'business_type' => 'retail',
            'nature_of_business' => 'No auth attempt',
        ]);

        $response->assertUnauthorized();
    }
}

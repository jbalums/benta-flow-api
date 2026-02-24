<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function signup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'OWNER',
            'auth_provider' => 'local',
        ]);

        $token = $user->createToken('pos-api')->plainTextToken;

        return $this->authResponse($user, $token, 'Signup successful.', 201);
    }

    public function signupWithGoogle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $googleUser = $this->verifyGoogleIdToken($validated['id_token']);

        if ($googleUser === null) {
            throw ValidationException::withMessages([
                'id_token' => ['Invalid Google ID token.'],
            ]);
        }

        $googleId = $googleUser['sub'];
        $email = $googleUser['email'];
        $displayName = $validated['name']
            ?? ($googleUser['name'] ?? Str::before($email, '@'));

        $user = User::query()->where('google_id', $googleId)->first();
        $created = false;

        if (!$user) {
            $user = User::query()->where('email', $email)->first();
        }

        if (!$user) {
            $user = User::create([
                'name' => $displayName,
                'email' => $email,
                'password' => Str::random(32),
                'role' => 'OWNER',
                'google_id' => $googleId,
                'auth_provider' => 'google',
                'email_verified_at' => now(),
            ]);

            $created = true;
        } else {
            if ($user->google_id && $user->google_id !== $googleId) {
                throw ValidationException::withMessages([
                    'id_token' => ['This Google account does not match the existing user profile.'],
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'auth_provider' => 'google',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        $token = $user->createToken('pos-api')->plainTextToken;

        return $this->authResponse(
            $user,
            $token,
            $created ? 'Signup successful.' : 'Login successful.',
            $created ? 201 : 200
        );
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Optional: revoke old tokens for single-session behavior
        // $user->tokens()->delete();

        $token = $user->createToken('pos-api')->plainTextToken;

        return $this->authResponse($user, $token, 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function upsertStoreDetails(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_type' => [
                'required',
                'string',
                'in:retail,wholesale,service,manufacturing,ecommerce,restaurant,other',
            ],
            'nature_of_business' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $store = Store::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $user->load('store');

        return response()->json([
            'message' => 'Store details saved successfully.',
            'store' => $store->fresh(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    private function authResponse(
        User $user,
        string $token,
        string $message,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'token' => $token,
            'user' => $this->userPayload($user),
        ], $status);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('store');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'auth_provider' => $user->auth_provider,
            'store' => $user->store,
            'has_completed_store_setup' => $user->store !== null,
        ];
    }

    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $response = Http::timeout(10)->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $idToken]
            );

            if (!$response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return null;
            }

            $clientId = config('services.google.client_id');
            if ($clientId && ($payload['aud'] ?? null) !== $clientId) {
                return null;
            }

            if (($payload['email_verified'] ?? null) !== 'true') {
                return null;
            }

            if (!isset($payload['sub'], $payload['email'])) {
                return null;
            }

            return $payload;
        } catch (Throwable) {
            return null;
        }
    }
}

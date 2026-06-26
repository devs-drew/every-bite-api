<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeValidToken(
        string $email = 'google@example.com',
        string $name = 'Google User',
        ?string $aud = null,
    ): void {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'email' => $email,
                'name'  => $name,
                'aud'   => $aud ?? config('services.google.client_id'),
                'sub'   => '112233445566',
            ], 200),
        ]);
    }

    private function fakeInvalidToken(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response(['error' => 'invalid_token'], 400),
        ]);
    }

    public function test_new_user_can_sign_in_with_google(): void
    {
        Http::preventStrayRequests();
        $this->fakeValidToken();

        $response = $this->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user', 'is_new_user'])
            ->assertJsonPath('is_new_user', true);

        $this->assertDatabaseHas('users', ['email' => 'google@example.com']);
    }

    public function test_existing_user_is_linked_not_duplicated(): void
    {
        Http::preventStrayRequests();
        User::factory()->create(['email' => 'google@example.com']);
        $this->fakeValidToken();

        $response = $this->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertStatus(200)
            ->assertJsonPath('is_new_user', false);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_token_returns_401(): void
    {
        Http::preventStrayRequests();
        $this->fakeInvalidToken();

        $response = $this->postJson('/api/auth/google', ['id_token' => 'bad-token']);

        $response->assertStatus(401);
    }

    public function test_audience_mismatch_returns_401(): void
    {
        Http::preventStrayRequests();
        $this->fakeValidToken(aud: 'wrong-client.apps.googleusercontent.com');

        $response = $this->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertStatus(401);
    }

    public function test_missing_id_token_returns_422(): void
    {
        $response = $this->postJson('/api/auth/google', []);

        $response->assertStatus(422);
    }
}

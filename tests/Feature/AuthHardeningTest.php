<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    // --- Rate limiting ---------------------------------------------------

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'a@example.com',
            'password' => Hash::make('secret123'),
        ]);

        // throttle:5,1 — the 6th attempt within a minute is blocked.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_register_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'name' => 'U', 'email' => "u{$i}@example.com",
                'password' => 'password123', 'password_confirmation' => 'password123',
            ]);
        }

        $this->postJson('/api/register', [
            'name' => 'U', 'email' => 'u99@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(429);
    }

    // --- Token expiry ----------------------------------------------------

    public function test_token_expires_after_the_configured_window(): void
    {
        $this->assertSame(43200, config('sanctum.expiration')); // 30 days, default

        $user = User::factory()->create();
        $token = $user->createToken('app')->plainTextToken;
        $auth = ['Authorization' => "Bearer {$token}"];

        // Single auth call after travelling past the window. (A prior in-process
        // request would cache the resolved user on Sanctum's RequestGuard and mask
        // expiry; production gets a fresh guard per request. Valid-token-before-expiry
        // is already covered by every other authenticated test.)
        $this->travel(43200 + 1)->minutes();
        $this->getJson('/api/user', $auth)->assertUnauthorized();
    }

    // --- Password reset --------------------------------------------------

    public function test_forgot_password_sends_a_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'a@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_leak_unknown_emails(): void
    {
        Notification::fake();

        // Still 200 (no user enumeration), but nothing is sent.
        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_reset_password_changes_the_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'a@example.com',
            'password' => Hash::make('oldpass123'),
        ]);
        $user->createToken('existing'); // should be revoked by the reset
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'a@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('newpass123', $fresh->password));
        $this->assertFalse(Hash::check('oldpass123', $fresh->password));
        $this->assertSame(0, $fresh->tokens()->count());
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'a@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}

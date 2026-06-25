<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $res = $this->postJson('/api/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonPath('user.daily_calorie_target', 2000); // schema default

        $user = User::firstWhere('email', 'jane@example.com');
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertArrayNotHasKey('password', $res->json('user'));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Dup',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_requires_password_confirmation_and_min_length(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'a@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'a@example.com',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'a@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'a@example.com',
            'password' => 'wrong',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_protected_route_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_authenticated_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'a@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'a@example.com',
            'password' => 'secret123',
        ])->json('token');

        $auth = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/user', $auth)->assertOk();
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/logout', [], $auth)->assertNoContent();

        // The token row is gone, so the bearer is dead for any future request.
        // (Asserting a follow-up 401 in-process is unreliable: Sanctum's RequestGuard
        // caches the resolved user across sub-requests in a single test run.)
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_update_profile_persists_and_validates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [
            'name' => 'Updated',
            'age' => 30,
            'weight_kg' => 72.5,
            'height_cm' => 175,
            'gender' => 'male',
            'activity_factor' => 1.55,
        ])->assertOk()
            ->assertJsonPath('name', 'Updated')
            ->assertJsonPath('weight_kg', 72.5)   // numeric, not string
            ->assertJsonPath('age', 30);

        // Bad enum / out-of-range rejected
        $this->putJson('/api/user/profile', ['gender' => 'other'])
            ->assertStatus(422)->assertJsonValidationErrors('gender');
        $this->putJson('/api/user/profile', ['age' => 200])
            ->assertStatus(422)->assertJsonValidationErrors('age');
    }

    public function test_update_goals_persists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user/goals', [
            'goal_direction' => 'lose',
            'calorie_adjustment' => -500,
            'goal_weight_kg' => 68,
            'daily_calorie_target' => 1800,
        ])->assertOk()
            ->assertJsonPath('goal_direction', 'lose')
            ->assertJsonPath('daily_calorie_target', 1800);
    }
}

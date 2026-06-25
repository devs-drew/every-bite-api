<?php

namespace Tests\Feature;

use App\Models\CustomFood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomFoodTest extends TestCase
{
    use RefreshDatabase;

    private function makeFood(User $user, array $attrs = []): CustomFood
    {
        return $user->customFoods()->create(array_merge([
            'food_name' => 'Homemade Granola',
            'serving_size_g' => 50,
            'calories' => 220,
            'protein_g' => 6,
            'carbs_g' => 30,
            'fat_g' => 9,
        ], $attrs));
    }

    public function test_index_returns_only_owners_foods_ordered_by_name(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeFood($user, ['food_name' => 'Zucchini Bread']);
        $this->makeFood($user, ['food_name' => 'Almond Butter']);
        $this->makeFood($other, ['food_name' => 'Not Mine']);

        Sanctum::actingAs($user);

        $this->getJson('/api/custom-foods')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.food_name', 'Almond Butter') // alphabetical
            ->assertJsonPath('1.food_name', 'Zucchini Bread');
    }

    public function test_store_creates_food_scoped_to_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/custom-foods', [
            'food_name' => 'Protein Shake',
            'calories' => 180,
            'protein_g' => 30,
        ])->assertCreated()->assertJsonPath('food_name', 'Protein Shake');

        $this->assertDatabaseHas('custom_foods', [
            'user_id' => $user->id,
            'food_name' => 'Protein Shake',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/custom-foods', ['protein_g' => 5])
            ->assertStatus(422)->assertJsonValidationErrors(['food_name', 'calories']);
    }

    public function test_user_cannot_update_another_users_food(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $food = $this->makeFood($owner, ['calories' => 220]);

        Sanctum::actingAs($attacker);
        $this->putJson("/api/custom-foods/{$food->id}", ['calories' => 1])->assertForbidden();
        $this->assertDatabaseHas('custom_foods', ['id' => $food->id, 'calories' => 220]);
    }

    public function test_user_cannot_delete_another_users_food(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $food = $this->makeFood($owner);

        Sanctum::actingAs($attacker);
        $this->deleteJson("/api/custom-foods/{$food->id}")->assertForbidden();
        $this->assertDatabaseHas('custom_foods', ['id' => $food->id]);
    }

    public function test_custom_foods_require_authentication(): void
    {
        $this->getJson('/api/custom-foods')->assertUnauthorized();
    }
}

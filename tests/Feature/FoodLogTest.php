<?php

namespace Tests\Feature;

use App\Models\FoodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FoodLogTest extends TestCase
{
    use RefreshDatabase;

    /** Create a food log for $user with sane defaults, overridable per test. */
    private function makeLog(User $user, array $attrs = []): FoodLog
    {
        return $user->foodLogs()->create(array_merge([
            'food_name' => 'Oatmeal',
            'meal_type' => 'breakfast',
            'logged_date' => '2026-06-20',
            'serving_qty' => 1,
            'serving_size_g' => 100,
            'calories' => 370,
            'protein_g' => 13,
            'carbs_g' => 66,
            'fat_g' => 7,
            'fiber_g' => 4,
        ], $attrs));
    }

    public function test_store_creates_log_scoped_to_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/food-logs', [
            'food_name' => 'Banana',
            'meal_type' => 'breakfast',
            'logged_date' => '2026-06-20',
            'serving_size_g' => 118,
            'calories' => 105,
            'protein_g' => 1.3,
        ]);

        $res->assertCreated()
            ->assertJsonPath('food_name', 'Banana')
            ->assertJsonPath('calories', 105);

        $this->assertDatabaseHas('food_logs', [
            'user_id' => $user->id,
            'food_name' => 'Banana',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/food-logs', ['brand_name' => 'Acme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['food_name', 'meal_type', 'logged_date', 'serving_size_g', 'calories']);
    }

    public function test_store_rejects_invalid_meal_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/food-logs', [
            'food_name' => 'X',
            'meal_type' => 'brunch',
            'logged_date' => '2026-06-20',
            'serving_size_g' => 10,
            'calories' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('meal_type');
    }

    public function test_index_returns_only_owners_logs_for_the_date(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeLog($user, ['food_name' => 'Mine', 'logged_date' => '2026-06-20']);
        $this->makeLog($user, ['food_name' => 'OtherDay', 'logged_date' => '2026-06-21']);
        $this->makeLog($other, ['food_name' => 'NotMine', 'logged_date' => '2026-06-20']);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/food-logs?date=2026-06-20');
        $res->assertOk()->assertJsonCount(1)->assertJsonPath('0.food_name', 'Mine');
    }

    public function test_index_filters_by_meal_type(): void
    {
        $user = User::factory()->create();
        $this->makeLog($user, ['meal_type' => 'breakfast']);
        $this->makeLog($user, ['meal_type' => 'lunch']);
        Sanctum::actingAs($user);

        $this->getJson('/api/food-logs?date=2026-06-20&meal_type=lunch')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.meal_type', 'lunch');
    }

    public function test_owner_can_update_their_log(): void
    {
        $user = User::factory()->create();
        $log = $this->makeLog($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/food-logs/{$log->id}", ['serving_size_g' => 200, 'calories' => 740])
            ->assertOk()
            ->assertJsonPath('calories', 740)
            ->assertJsonPath('serving_size_g', 200);
    }

    public function test_user_cannot_update_another_users_log(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $log = $this->makeLog($owner, ['calories' => 370]);

        Sanctum::actingAs($attacker);
        $this->putJson("/api/food-logs/{$log->id}", ['calories' => 1])->assertForbidden();

        $this->assertDatabaseHas('food_logs', ['id' => $log->id, 'calories' => 370]);
    }

    public function test_owner_can_delete_their_log(): void
    {
        $user = User::factory()->create();
        $log = $this->makeLog($user);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/food-logs/{$log->id}")->assertNoContent();
        $this->assertDatabaseMissing('food_logs', ['id' => $log->id]);
    }

    public function test_user_cannot_delete_another_users_log(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $log = $this->makeLog($owner);

        Sanctum::actingAs($attacker);
        $this->deleteJson("/api/food-logs/{$log->id}")->assertForbidden();
        $this->assertDatabaseHas('food_logs', ['id' => $log->id]);
    }

    public function test_summary_totals_and_meal_buckets(): void
    {
        $user = User::factory()->create(['daily_calorie_target' => 2100]);
        // breakfast 370 + 105, lunch 247 — dinner/snack empty
        $this->makeLog($user, ['meal_type' => 'breakfast', 'calories' => 370, 'protein_g' => 13, 'carbs_g' => 66, 'fat_g' => 7, 'fiber_g' => 4]);
        $this->makeLog($user, ['meal_type' => 'breakfast', 'calories' => 105, 'protein_g' => 1.3, 'carbs_g' => 27, 'fat_g' => 0.4, 'fiber_g' => 3.1]);
        $this->makeLog($user, ['meal_type' => 'lunch', 'calories' => 247, 'protein_g' => 46, 'carbs_g' => 0, 'fat_g' => 5.4, 'fiber_g' => 0]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/food-logs/summary?date=2026-06-20')->assertOk();

        $res->assertJsonPath('date', '2026-06-20')
            ->assertJsonPath('daily_target', 2100)
            ->assertJsonPath('totals.calories', 722)
            ->assertJsonPath('totals.protein_g', 60.3)
            ->assertJsonPath('totals.fiber_g', 7.1)
            ->assertJsonPath('by_meal.breakfast.calories', 475)
            ->assertJsonPath('by_meal.lunch.calories', 247)
            ->assertJsonPath('by_meal.dinner.calories', 0)
            ->assertJsonPath('by_meal.snack.calories', 0);
    }

    public function test_summary_for_empty_day_is_all_zeros(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/food-logs/summary?date=2026-06-20')
            ->assertOk()
            ->assertJsonPath('totals.calories', 0)
            ->assertJsonPath('by_meal.dinner.calories', 0);
    }

    public function test_history_groups_calories_by_date(): void
    {
        $user = User::factory()->create();
        $this->makeLog($user, ['logged_date' => '2026-06-19', 'calories' => 1000]);
        $this->makeLog($user, ['logged_date' => '2026-06-20', 'calories' => 600]);
        $this->makeLog($user, ['logged_date' => '2026-06-20', 'calories' => 400]); // same day -> summed
        $this->makeLog($user, ['logged_date' => '2026-06-25', 'calories' => 9999]); // outside range
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/food-logs/history?from=2026-06-19&to=2026-06-21')->assertOk();

        $res->assertJsonCount(2)
            ->assertJsonPath('0.date', '2026-06-19')
            ->assertJsonPath('0.calories', 1000)
            ->assertJsonPath('1.date', '2026-06-20')
            ->assertJsonPath('1.calories', 1000); // 600 + 400
    }

    public function test_history_requires_valid_range(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/food-logs/history?from=2026-06-21&to=2026-06-19')
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo account matching the frontend's loginAsDemo defaults (stores/auth.ts),
        // so the same credentials work against the real API. Password: "password".
        User::updateOrCreate(
            ['email' => 'demo@everybite.app'],
            [
                'name' => 'Demo User',
                'password' => 'password',
                'age' => 28,
                'weight_kg' => 75,
                'height_cm' => 175,
                'gender' => 'male',
                'activity_factor' => 1.55,
                'goal_direction' => 'lose',
                'calorie_adjustment' => -500,
                'goal_weight_kg' => 70,
                'daily_calorie_target' => 2022,
            ],
        );
    }
}

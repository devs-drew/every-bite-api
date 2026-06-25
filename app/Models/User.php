<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password',
    'age', 'weight_kg', 'height_cm', 'gender', 'activity_factor',
    'goal_direction', 'calorie_adjustment', 'goal_weight_kg', 'daily_calorie_target',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Postgres returns numerics as strings; cast so JSON emits numbers
            // (the frontend does math on these — AddFoodModal, macro bars).
            'weight_kg' => 'float',
            'height_cm' => 'float',
            'activity_factor' => 'float',
            'goal_weight_kg' => 'float',
            'age' => 'integer',
            'calorie_adjustment' => 'integer',
            'daily_calorie_target' => 'integer',
        ];
    }

    public function foodLogs(): HasMany
    {
        return $this->hasMany(FoodLog::class);
    }

    public function customFoods(): HasMany
    {
        return $this->hasMany(CustomFood::class);
    }
}

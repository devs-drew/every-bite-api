<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'food_name', 'brand_name', 'barcode', 'off_product_id', 'meal_type',
    'logged_date', 'serving_qty', 'serving_size_g',
    'calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g',
])]
class FoodLog extends Model
{
    protected function casts(): array
    {
        return [
            'logged_date' => 'date:Y-m-d',
            // Cast to numbers so JSON doesn't emit Postgres string numerics.
            'serving_qty' => 'float',
            'serving_size_g' => 'float',
            'calories' => 'integer',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

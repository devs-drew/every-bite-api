<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Denormalized log: macros are computed by the frontend and stored per entry
// (no foods/products table — see backend plan). Mirrors FoodLog, stores/foodLog.ts.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('food_name');
            $table->string('brand_name')->nullable();
            $table->string('barcode')->nullable();
            $table->string('off_product_id')->nullable();
            $table->string('meal_type'); // breakfast | lunch | dinner | snack
            $table->date('logged_date');
            $table->float('serving_qty')->default(1);
            $table->float('serving_size_g');
            $table->integer('calories');
            $table->float('protein_g')->nullable();
            $table->float('carbs_g')->nullable();
            $table->float('fat_g')->nullable();
            $table->float('fiber_g')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_logs');
    }
};

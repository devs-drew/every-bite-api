<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// User-defined foods. Nutrients stored per 100g, mirroring FoodResult
// (stores/foodSearch.ts). Built to the customFood.service.ts contract;
// no UI consumes it yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('food_name');
            $table->string('brand_name')->nullable();
            $table->string('barcode')->nullable();
            $table->float('serving_size_g')->default(100);
            $table->integer('calories');           // per 100g
            $table->float('protein_g')->nullable();
            $table->float('carbs_g')->nullable();
            $table->float('fat_g')->nullable();
            $table->float('fiber_g')->nullable();
            $table->float('sugar_g')->nullable();
            $table->integer('sodium_mg')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_foods');
    }
};

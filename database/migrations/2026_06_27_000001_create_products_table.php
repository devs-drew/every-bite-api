<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();   // barcode; upsert key
            $table->string('product_name', 500);
            $table->string('brand_name', 500)->nullable();
            $table->float('serving_size_g')->nullable();
            $table->integer('calories')->nullable();   // per 100g
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
        Schema::dropIfExists('products');
    }
};

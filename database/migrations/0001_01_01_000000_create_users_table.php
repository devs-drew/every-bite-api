<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Profile + goal fields (mirror frontend User interface, stores/auth.ts).
            // Stored metric; the app converts imperial for display only.
            $table->unsignedSmallInteger('age')->nullable();
            $table->float('weight_kg')->nullable();
            $table->float('height_cm')->nullable();
            $table->string('gender')->nullable();              // 'male' | 'female'
            $table->float('activity_factor')->nullable();      // 1.2 .. 1.9
            $table->string('goal_direction')->nullable();      // 'lose' | 'maintain' | 'gain'
            $table->integer('calorie_adjustment')->nullable(); // +/- kcal vs TDEE
            $table->float('goal_weight_kg')->nullable();
            $table->integer('daily_calorie_target')->default(2000);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

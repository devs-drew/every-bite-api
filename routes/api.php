<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomFoodController;
use App\Http\Controllers\FoodLogController;
use Illuminate\Support\Facades\Route;

// Public auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth + profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/goals', [AuthController::class, 'updateGoals']);

    // Food logs (specific routes before the {foodLog} wildcard)
    Route::get('/food-logs/summary', [FoodLogController::class, 'summary']);
    Route::get('/food-logs/history', [FoodLogController::class, 'history']);
    Route::get('/food-logs', [FoodLogController::class, 'index']);
    Route::post('/food-logs', [FoodLogController::class, 'store']);
    Route::put('/food-logs/{foodLog}', [FoodLogController::class, 'update']);
    Route::delete('/food-logs/{foodLog}', [FoodLogController::class, 'destroy']);

    // Custom foods
    Route::apiResource('custom-foods', CustomFoodController::class)
        ->parameters(['custom-foods' => 'customFood'])
        ->only(['index', 'store', 'update', 'destroy']);
});

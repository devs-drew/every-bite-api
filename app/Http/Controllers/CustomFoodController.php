<?php

namespace App\Http\Controllers;

use App\Models\CustomFood;
use Illuminate\Http\Request;

class CustomFoodController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->customFoods()->orderBy('food_name')->get();
    }

    public function store(Request $request)
    {
        $food = $request->user()->customFoods()->create($this->validateFood($request));

        return response()->json($food, 201);
    }

    public function update(Request $request, CustomFood $customFood)
    {
        $this->authorizeOwner($request, $customFood);
        $customFood->update($this->validateFood($request, partial: true));

        return $customFood->fresh();
    }

    public function destroy(Request $request, CustomFood $customFood)
    {
        $this->authorizeOwner($request, $customFood);
        $customFood->delete();

        return response()->noContent();
    }

    private function validateFood(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'food_name' => [$req, 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'serving_size_g' => ['nullable', 'numeric', 'min:0'],
            'calories' => [$req, 'integer', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'fiber_g' => ['nullable', 'numeric', 'min:0'],
            'sugar_g' => ['nullable', 'numeric', 'min:0'],
            'sodium_mg' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function authorizeOwner(Request $request, CustomFood $customFood): void
    {
        abort_unless($customFood->user_id === $request->user()->id, 403);
    }
}

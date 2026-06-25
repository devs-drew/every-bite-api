<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'daily_calorie_target' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = User::create($data);

        return response()->json([
            'token' => $user->createToken('app')->plainTextToken,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('app')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function user(Request $request)
    {
        return $request->user();
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'gender' => ['nullable', 'in:male,female'],
            'activity_factor' => ['nullable', 'numeric', 'min:1', 'max:2.5'],
        ]);

        $request->user()->update($data);

        return $request->user()->fresh();
    }

    public function updateGoals(Request $request)
    {
        $data = $request->validate([
            'goal_direction' => ['nullable', 'in:lose,maintain,gain'],
            'calorie_adjustment' => ['nullable', 'integer'],
            'goal_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'daily_calorie_target' => ['nullable', 'integer', 'min:0'],
        ]);

        $request->user()->update($data);

        return $request->user()->fresh();
    }
}

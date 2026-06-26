<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
            'user' => $user->fresh(), // reflect DB defaults (daily_calorie_target) like /login does
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

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Always generic — don't reveal whether the email is registered.
        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete(); // revoke existing API tokens on password change
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
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

    public function googleSignIn(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['id_token' => 'required|string']);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);

        if (!$response->ok()) {
            return response()->json(['message' => 'Invalid Google token.'], 401);
        }

        $payload = $response->json();

        if (($payload['aud'] ?? '') !== config('services.google.client_id')) {
            return response()->json(['message' => 'Token audience mismatch.'], 401);
        }

        if (($payload['email_verified'] ?? '') !== 'true' && ($payload['email_verified'] ?? false) !== true) {
            return response()->json(['message' => 'Email not verified.'], 401);
        }

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'name'     => $payload['name'] ?? 'Google User',
                'password' => bcrypt(Str::random(32)),
            ],
        );

        return response()->json([
            'token'       => $user->createToken('google')->plainTextToken,
            'user'        => $user->fresh(),
            'is_new_user' => $user->wasRecentlyCreated,
        ]);
    }
}

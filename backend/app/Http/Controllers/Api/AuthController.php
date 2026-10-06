<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $token = $this->createToken($user);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $token = $this->createToken($user);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->password;
        $user->save();

        // Revoke all other sessions (tokens); keep the current one so the user stays logged in.
        $currentToken = $user->currentAccessToken();
        $tokens = $user->tokens();
        if ($currentToken instanceof PersonalAccessToken) {
            $tokens->where('id', '!=', $currentToken->id);
        }
        $tokens->delete();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(request()->user()),
        ]);
    }

    private function createToken(User $user): string
    {
        $expirationMinutes = (int) config('sanctum.new_token_expiration', 43200);
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        return $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;
    }
}

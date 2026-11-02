<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentification de l'administration par jeton Bearer (Sanctum).
 * Chaque connexion crée un jeton nommé (device_name) révocable.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->value())->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
                'code' => 'invalid_credentials',
                'errors' => ['email' => ['L’adresse e-mail ou le mot de passe est incorrect.']],
                'request_id' => $request->attributes->get('request_id'),
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'Ce compte est désactivé. Contactez un administrateur.',
                'code' => 'account_disabled',
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        }

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 480));
        $token = $user->createToken($request->deviceName(), ['admin'], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => new AdminUserResource($user),
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new AdminUserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }
}

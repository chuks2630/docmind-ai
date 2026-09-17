<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RefreshRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\UserSummaryResource;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * api-specification.md §3 Auth & Identity endpoints.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());
        $tokens = $this->auth->issueTokenPair($user);

        return response()->json([
            'user' => new UserSummaryResource($user),
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'expires_at' => $tokens->expiresAt->toIso8601String(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->attemptLogin($request->validated());
        $tokens = $this->auth->issueTokenPair($user);

        return response()->json([
            'user' => new UserSummaryResource($user),
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'expires_at' => $tokens->expiresAt->toIso8601String(),
        ], 200);
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $refreshed = $this->auth->refresh($request->validated('refresh_token'));

        return response()->json([
            'access_token' => $refreshed->accessToken,
            'expires_at' => $refreshed->expiresAt->toIso8601String(),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}

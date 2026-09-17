<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_a_new_access_token_from_a_valid_refresh_token(): void
    {
        $user = User::factory()->create();
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(30))->plainTextToken;

        $response = $this->postJson('/v1/auth/refresh', ['refresh_token' => $refreshToken]);

        $response->assertOk()->assertJsonStructure(['access_token', 'expires_at']);

        // The new access token actually authenticates against a protected endpoint.
        $this->withToken($response->json('access_token'))
            ->getJson('/v1/me')
            ->assertOk();
    }

    public function test_rejects_an_expired_refresh_token_with_401_not_a_partial_success(): void
    {
        $user = User::factory()->create();
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->subMinute())->plainTextToken;

        $response = $this->postJson('/v1/auth/refresh', ['refresh_token' => $refreshToken]);

        $response->assertStatus(401);
        $this->assertSame('invalid_refresh_token', $response->json('error.code'));
        $this->assertFalse($response->json('error.recoverable'));
        $this->assertArrayNotHasKey('access_token', $response->json());
    }

    public function test_rejects_a_garbage_refresh_token_with_401(): void
    {
        $response = $this->postJson('/v1/auth/refresh', ['refresh_token' => 'not-a-real-token']);

        $response->assertStatus(401);
        $this->assertSame('invalid_refresh_token', $response->json('error.code'));
    }

    public function test_an_access_token_cannot_be_used_in_place_of_a_refresh_token(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('access_token', ['access'], now()->addHour())->plainTextToken;

        $response = $this->postJson('/v1/auth/refresh', ['refresh_token' => $accessToken]);

        $response->assertStatus(401);
        $this->assertSame('invalid_refresh_token', $response->json('error.code'));
    }

    public function test_a_refresh_token_cannot_be_used_to_access_protected_endpoints(): void
    {
        $user = User::factory()->create();
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(30))->plainTextToken;

        $this->withToken($refreshToken)
            ->getJson('/v1/me')
            ->assertStatus(403);
    }
}

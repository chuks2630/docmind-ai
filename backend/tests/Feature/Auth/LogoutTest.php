<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('access_token', ['access'], now()->addHour())->plainTextToken;

        $this->withToken($accessToken)->postJson('/v1/auth/logout')->assertNoContent();

        // Sanctum's guard caches the resolved user for the life of the guard
        // instance; forget it so this second call re-authenticates from the DB
        // instead of reusing the first request's in-memory result.
        $this->app['auth']->forgetGuards();

        $this->withToken($accessToken)->getJson('/v1/me')->assertStatus(401);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/v1/auth/logout')->assertStatus(401);
    }
}

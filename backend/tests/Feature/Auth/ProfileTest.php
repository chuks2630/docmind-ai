<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_me_returns_the_current_user_profile(): void
    {
        $user = User::factory()->create(['display_name' => 'Farah Q.']);
        $accessToken = $user->createToken('access_token', ['access'], now()->addHour())->plainTextToken;

        $response = $this->withToken($accessToken)->getJson('/v1/me');

        $response->assertOk()->assertJson([
            'id' => (string) $user->id,
            'display_name' => 'Farah Q.',
            'email' => $user->email,
        ])->assertJsonStructure(['id', 'display_name', 'email', 'created_at']);
    }

    public function test_get_me_requires_authentication(): void
    {
        $this->getJson('/v1/me')->assertStatus(401);
    }

    public function test_delete_me_returns_202_and_revokes_access_immediately(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('access_token', ['access'], now()->addHour())->plainTextToken;

        $this->withToken($accessToken)->deleteJson('/v1/me')->assertStatus(202);

        // Sanctum's guard caches the resolved user for the life of the guard
        // instance; forget it so this second call re-authenticates from the DB
        // instead of reusing the first request's in-memory result.
        $this->app['auth']->forgetGuards();

        // Access is revoked immediately even though the physical deletion is scheduled/async.
        $this->withToken($accessToken)->getJson('/v1/me')->assertStatus(401);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}

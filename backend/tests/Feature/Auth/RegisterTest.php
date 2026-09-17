<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Support\ProviderIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FakesProviderTokens;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use FakesProviderTokens, RefreshDatabase;

    public function test_registers_a_new_account_with_email_and_password(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'email',
            'email' => 'sana@example.com',
            'password' => 'super-secret',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'user' => ['id', 'display_name', 'email'],
            'access_token',
            'refresh_token',
            'expires_at',
        ]);

        $this->assertSame('sana@example.com', $response->json('user.email'));

        $this->assertDatabaseHas('users', [
            'email' => 'sana@example.com',
            'auth_provider' => 'email',
        ]);

        $user = User::where('email', 'sana@example.com')->firstOrFail();
        $this->assertNotNull($user->password_hash);
        $this->assertTrue(Hash::check('super-secret', $user->password_hash));
    }

    public function test_registers_via_apple_after_verifying_the_provider_token(): void
    {
        $this->fakeProviderToken('apple', 'valid-apple-token', new ProviderIdentity('apple-sub-1', 'daniel@example.com'));

        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'apple',
            'provider_token' => 'valid-apple-token',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'auth_provider' => 'apple',
            'auth_provider_id' => 'apple-sub-1',
            'email' => 'daniel@example.com',
        ]);
    }

    public function test_registers_via_google_after_verifying_the_provider_token(): void
    {
        $this->fakeProviderToken('google', 'valid-google-token', new ProviderIdentity('google-sub-1', 'maria@example.com'));

        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'google',
            'provider_token' => 'valid-google-token',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'auth_provider' => 'google',
            'auth_provider_id' => 'google-sub-1',
            'email' => 'maria@example.com',
        ]);
    }

    public function test_rejects_an_unverifiable_provider_token(): void
    {
        $this->fakeProviderToken('apple', 'valid-apple-token', new ProviderIdentity('apple-sub-1'));

        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'apple',
            'provider_token' => 'not-the-valid-token',
        ]);

        $response->assertStatus(401)->assertJson([
            'error' => ['code' => 'invalid_credentials', 'recoverable' => true],
        ]);
    }

    public function test_stores_the_guest_device_id_for_later_migration(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'email',
            'email' => 'farah@example.com',
            'password' => 'super-secret',
            'guest_device_id' => '9f8e7d6c-1234-4abc-8def-0123456789ab',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'farah@example.com',
            'guest_device_id' => '9f8e7d6c-1234-4abc-8def-0123456789ab',
        ]);
    }

    public function test_returns_validation_failed_envelope_for_a_short_password(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'email',
            'email' => 'amaro@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonStructure([
            'error' => ['code', 'message', 'recoverable', 'fields'],
        ]);
        $this->assertSame('validation_failed', $response->json('error.code'));
        $this->assertArrayHasKey('password', $response->json('error.fields'));
    }

    public function test_returns_validation_failed_envelope_for_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'email',
            'email' => 'duplicate@example.com',
            'password' => 'super-secret',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response->json('error.fields'));
    }

    public function test_returns_validation_failed_envelope_for_a_malformed_email(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'provider' => 'email',
            'email' => 'not-an-email',
            'password' => 'super-secret',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response->json('error.fields'));
    }
}

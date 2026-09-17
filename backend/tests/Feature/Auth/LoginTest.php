<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Support\ProviderIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\FakesProviderTokens;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use FakesProviderTokens, RefreshDatabase;

    public function test_logs_in_with_email_and_password(): void
    {
        User::factory()->create([
            'email' => 'sana@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/v1/auth/login', [
            'provider' => 'email',
            'email' => 'sana@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonStructure([
            'user' => ['id', 'display_name', 'email'],
            'access_token',
            'refresh_token',
            'expires_at',
        ]);
    }

    public function test_rejects_an_incorrect_password_with_401(): void
    {
        User::factory()->create([
            'email' => 'sana@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/v1/auth/login', [
            'provider' => 'email',
            'email' => 'sana@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)->assertJson([
            'error' => ['code' => 'invalid_credentials', 'recoverable' => true],
        ]);
    }

    public function test_rejects_an_unknown_email_with_401(): void
    {
        $response = $this->postJson('/v1/auth/login', [
            'provider' => 'email',
            'email' => 'nobody@example.com',
            'password' => 'whatever-password',
        ]);

        $response->assertStatus(401);
        $this->assertSame('invalid_credentials', $response->json('error.code'));
    }

    public function test_logs_in_with_a_previously_registered_apple_account(): void
    {
        User::factory()->withProvider('apple', 'apple-sub-1')->create();
        $this->fakeProviderToken('apple', 'valid-apple-token', new ProviderIdentity('apple-sub-1'));

        $response = $this->postJson('/v1/auth/login', [
            'provider' => 'apple',
            'provider_token' => 'valid-apple-token',
        ]);

        $response->assertOk();
    }

    public function test_logs_in_with_a_previously_registered_google_account(): void
    {
        User::factory()->withProvider('google', 'google-sub-1')->create();
        $this->fakeProviderToken('google', 'valid-google-token', new ProviderIdentity('google-sub-1'));

        $response = $this->postJson('/v1/auth/login', [
            'provider' => 'google',
            'provider_token' => 'valid-google-token',
        ]);

        $response->assertOk();
    }

    public function test_rejects_a_provider_identity_with_no_matching_account(): void
    {
        $this->fakeProviderToken('apple', 'valid-apple-token', new ProviderIdentity('never-registered-sub'));

        $response = $this->postJson('/v1/auth/login', [
            'provider' => 'apple',
            'provider_token' => 'valid-apple-token',
        ]);

        $response->assertStatus(401);
        $this->assertSame('invalid_credentials', $response->json('error.code'));
    }
}

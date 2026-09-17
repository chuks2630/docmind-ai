<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Services\AppleTokenVerifier;
use App\Modules\Auth\Services\GoogleTokenVerifier;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Auth module's provider token verifiers behind named container
 * keys (rather than a hard switch statement in the factory), so tests can
 * swap in fakes without making real calls to Apple/Google.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('auth.provider_verifier.apple', AppleTokenVerifier::class);
        $this->app->bind('auth.provider_verifier.google', GoogleTokenVerifier::class);
    }
}

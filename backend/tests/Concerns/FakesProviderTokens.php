<?php

namespace Tests\Concerns;

use App\Modules\Auth\Support\ProviderIdentity;
use Tests\Support\FakeProviderTokenVerifier;

trait FakesProviderTokens
{
    protected function fakeProviderToken(string $provider, string $validToken, ProviderIdentity $identity): void
    {
        $this->app->bind(
            "auth.provider_verifier.{$provider}",
            fn () => new FakeProviderTokenVerifier($validToken, $identity),
        );
    }
}

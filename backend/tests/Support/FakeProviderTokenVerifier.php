<?php

namespace Tests\Support;

use App\Modules\Auth\Contracts\ProviderTokenVerifier;
use App\Modules\Auth\Exceptions\InvalidProviderTokenException;
use App\Modules\Auth\Support\ProviderIdentity;

/**
 * Test double bound over the real Apple/Google verifiers so feature tests
 * exercise the register/login flow without making live network calls to
 * either provider. `token` is treated as a stand-in for a valid identity
 * token; anything else fails verification, mirroring the real contract.
 */
class FakeProviderTokenVerifier implements ProviderTokenVerifier
{
    public function __construct(
        private readonly string $validToken,
        private readonly ProviderIdentity $identity,
    ) {}

    public function verify(string $token): ProviderIdentity
    {
        if (! hash_equals($this->validToken, $token)) {
            throw new InvalidProviderTokenException;
        }

        return $this->identity;
    }
}

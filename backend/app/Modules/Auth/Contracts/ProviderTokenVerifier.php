<?php

namespace App\Modules\Auth\Contracts;

use App\Modules\Auth\Exceptions\InvalidProviderTokenException;
use App\Modules\Auth\Support\ProviderIdentity;

interface ProviderTokenVerifier
{
    /**
     * Verify a client-supplied identity token server-side against the provider's
     * public keys and return the identity it attests to.
     *
     * @throws InvalidProviderTokenException
     */
    public function verify(string $token): ProviderIdentity;
}

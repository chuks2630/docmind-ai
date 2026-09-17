<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Contracts\ProviderTokenVerifier;
use App\Modules\Auth\Exceptions\InvalidProviderTokenException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the token verifier for a given provider name through the container
 * (bound in App\Modules\Auth\Providers\AuthServiceProvider), rather than a hard
 * switch statement — so tests can swap in fakes without touching real network calls.
 */
class ProviderTokenVerifierFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $provider): ProviderTokenVerifier
    {
        return match ($provider) {
            'apple', 'google' => $this->container->make("auth.provider_verifier.{$provider}"),
            default => throw new InvalidProviderTokenException("Unsupported auth provider [{$provider}]."),
        };
    }
}

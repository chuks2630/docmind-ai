<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Contracts\ProviderTokenVerifier;
use App\Modules\Auth\Exceptions\InvalidProviderTokenException;
use App\Modules\Auth\Support\ProviderIdentity;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies a Google Sign-In identity token server-side against Google's
 * published public keys (Technical Architecture §9) rather than trusting the
 * client-supplied token's claims as-is.
 */
class GoogleTokenVerifier implements ProviderTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public function verify(string $token): ProviderIdentity
    {
        try {
            $keys = JWK::parseKeySet($this->fetchKeySet());
            $decoded = JWT::decode($token, $keys);
        } catch (Throwable) {
            throw new InvalidProviderTokenException('Your Google sign-in could not be verified.');
        }

        if (! in_array($decoded->iss ?? null, self::ISSUERS, true)
            || ($decoded->aud ?? null) !== config('services.google.client_id')
            || empty($decoded->sub)
        ) {
            throw new InvalidProviderTokenException('Your Google sign-in could not be verified.');
        }

        return new ProviderIdentity(
            providerId: $decoded->sub,
            email: $decoded->email ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchKeySet(): array
    {
        return Cache::remember('auth.google.jwks', now()->addHours(6), fn () => Http::throw()->get(self::JWKS_URL)->json());
    }
}

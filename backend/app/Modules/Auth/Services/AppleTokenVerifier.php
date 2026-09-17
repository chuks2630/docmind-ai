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
 * Verifies a Sign in with Apple identity token server-side against Apple's
 * published public keys (Technical Architecture §9) rather than trusting the
 * client-supplied token's claims as-is.
 */
class AppleTokenVerifier implements ProviderTokenVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const ISSUER = 'https://appleid.apple.com';

    public function verify(string $token): ProviderIdentity
    {
        try {
            $keys = JWK::parseKeySet($this->fetchKeySet());
            $decoded = JWT::decode($token, $keys);
        } catch (Throwable) {
            throw new InvalidProviderTokenException('Your Apple sign-in could not be verified.');
        }

        $audiences = array_filter(array_map('trim', explode(',', (string) config('services.apple.client_id'))));
        $tokenAudiences = (array) ($decoded->aud ?? []);

        if (($decoded->iss ?? null) !== self::ISSUER
            || empty(array_intersect($audiences, $tokenAudiences))
            || empty($decoded->sub)
        ) {
            throw new InvalidProviderTokenException('Your Apple sign-in could not be verified.');
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
        return Cache::remember('auth.apple.jwks', now()->addHours(6), fn () => Http::throw()->get(self::JWKS_URL)->json());
    }
}

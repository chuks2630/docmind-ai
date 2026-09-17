<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Exceptions\InvalidRefreshTokenException;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Support\RefreshedToken;
use App\Modules\Auth\Support\TokenPair;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registration/login/token-lifecycle logic for the Auth & Identity module
 * (Technical Architecture §5.1, §9).
 */
class AuthService
{
    private const ACCESS_TOKEN_TTL_MINUTES = 60;

    private const REFRESH_TOKEN_TTL_DAYS = 30;

    private const ACCESS_ABILITY = 'access';

    private const REFRESH_ABILITY = 'refresh';

    public function __construct(private readonly ProviderTokenVerifierFactory $verifiers) {}

    /**
     * @param  array{provider: string, provider_token?: string, email?: string, password?: string, guest_device_id?: string}  $data
     */
    public function register(array $data): User
    {
        $user = $data['provider'] === 'email'
            ? $this->registerWithEmail($data['email'], $data['password'])
            : $this->findOrCreateFromProvider($data['provider'], $data['provider_token']);

        if (! empty($data['guest_device_id'])) {
            // Placeholder for Sprint 0 (Technical Architecture §9): the actual
            // guest-data migration depends on documents/folders, which land in a
            // later sprint. For now we just record the mapping.
            $user->forceFill(['guest_device_id' => $data['guest_device_id']])->save();
        }

        return $user;
    }

    /**
     * @param  array{provider: string, provider_token?: string, email?: string, password?: string}  $data
     *
     * @throws InvalidCredentialsException
     */
    public function attemptLogin(array $data): User
    {
        return $data['provider'] === 'email'
            ? $this->loginWithEmail($data['email'], $data['password'])
            : $this->loginWithProvider($data['provider'], $data['provider_token']);
    }

    public function issueTokenPair(User $user): TokenPair
    {
        $expiresAt = now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES)->toImmutable();

        $access = $user->createToken('access_token', [self::ACCESS_ABILITY], $expiresAt);
        $refresh = $user->createToken('refresh_token', [self::REFRESH_ABILITY], now()->addDays(self::REFRESH_TOKEN_TTL_DAYS));

        return new TokenPair(
            accessToken: $access->plainTextToken,
            refreshToken: $refresh->plainTextToken,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Silent refresh (Technical Architecture §9): issues a new short-lived access
     * token from a still-valid refresh token. Never rotates the refresh token —
     * api-specification.md §3 only returns a new access_token/expires_at pair.
     *
     * @throws InvalidRefreshTokenException
     */
    public function refresh(string $refreshToken): RefreshedToken
    {
        $token = PersonalAccessToken::findToken($refreshToken);

        if (! $token
            || ! $token->can(self::REFRESH_ABILITY)
            || ($token->expires_at && $token->expires_at->isPast())
            || ! $token->tokenable
        ) {
            throw new InvalidRefreshTokenException;
        }

        /** @var User $user */
        $user = $token->tokenable;

        $expiresAt = now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES)->toImmutable();
        $access = $user->createToken('access_token', [self::ACCESS_ABILITY], $expiresAt);

        return new RefreshedToken($access->plainTextToken, $expiresAt);
    }

    /**
     * Account deletion (Technical Architecture §14): access is revoked
     * immediately by deleting all tokens; the account is soft-deleted pending
     * its scheduled hard-delete (not built in this sprint).
     */
    public function deleteAccount(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }

    private function registerWithEmail(string $email, string $password): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => Hash::make($password),
            'auth_provider' => 'email',
            'display_name' => Str::before($email, '@'),
        ]);
    }

    private function loginWithEmail(string $email, string $password): User
    {
        $user = User::where('auth_provider', 'email')->where('email', $email)->first();

        if (! $user || ! $user->password_hash || ! Hash::check($password, $user->password_hash)) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }

    private function findOrCreateFromProvider(string $provider, string $providerToken): User
    {
        $identity = $this->verifiers->make($provider)->verify($providerToken);

        return User::firstOrCreate(
            ['auth_provider' => $provider, 'auth_provider_id' => $identity->providerId],
            [
                'email' => $identity->email,
                'display_name' => $identity->email ? Str::before($identity->email, '@') : 'DocMind User',
            ],
        );
    }

    private function loginWithProvider(string $provider, string $providerToken): User
    {
        $identity = $this->verifiers->make($provider)->verify($providerToken);

        $user = User::where('auth_provider', $provider)
            ->where('auth_provider_id', $identity->providerId)
            ->first();

        if (! $user) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }
}

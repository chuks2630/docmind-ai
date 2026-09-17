<?php

namespace App\Modules\Auth\Support;

use Carbon\CarbonImmutable;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonImmutable $expiresAt,
    ) {}
}

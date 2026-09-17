<?php

namespace App\Modules\Auth\Support;

use Carbon\CarbonImmutable;

final readonly class RefreshedToken
{
    public function __construct(
        public string $accessToken,
        public CarbonImmutable $expiresAt,
    ) {}
}

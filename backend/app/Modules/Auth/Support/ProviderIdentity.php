<?php

namespace App\Modules\Auth\Support;

/**
 * The identity claims extracted from a verified Apple/Google identity token.
 */
final readonly class ProviderIdentity
{
    public function __construct(
        public string $providerId,
        public ?string $email = null,
    ) {}
}

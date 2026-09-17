<?php

namespace App\Modules\Auth\Exceptions;

use App\Support\Exceptions\ApiException;

/**
 * The client-supplied Apple/Google identity token failed server-side
 * verification (bad signature, wrong issuer/audience, or expired) — it is
 * never trusted as-is. Rendered as the same 401 invalid_credentials contract
 * as a failed email/password login, since both mean "we could not authenticate you."
 */
class InvalidProviderTokenException extends ApiException
{
    public function __construct(string $message = 'We could not verify your sign-in with that provider.')
    {
        parent::__construct(
            errorCode: 'invalid_credentials',
            message: $message,
            status: 401,
        );
    }
}

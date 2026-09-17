<?php

namespace App\Modules\Auth\Exceptions;

use App\Support\Exceptions\ApiException;

/**
 * The refresh token is missing, expired, or revoked. Technical Architecture §9:
 * refresh failures force re-authentication rather than degrading silently, so
 * this is `recoverable: false` — the client should not retry, it should sign in again.
 */
class InvalidRefreshTokenException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'invalid_refresh_token',
            message: 'Your session has expired. Please sign in again.',
            status: 401,
            recoverable: false,
        );
    }
}

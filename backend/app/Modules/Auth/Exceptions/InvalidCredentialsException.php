<?php

namespace App\Modules\Auth\Exceptions;

use App\Support\Exceptions\ApiException;

/**
 * Login failed: no matching account, wrong password, or an unverifiable
 * provider identity. api-specification.md §3 login contract — 401 invalid_credentials.
 */
class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'invalid_credentials',
            message: 'The email or password you entered is incorrect.',
            status: 401,
        );
    }
}

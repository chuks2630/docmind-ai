<?php

namespace App\Support\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base for exceptions that should render as the shared API error envelope
 * (api-specification.md §2), instead of Laravel's default error formats.
 * Laravel calls render() on any thrown exception that defines it, so
 * subclasses need no per-controller or per-handler wiring.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra  additional envelope fields, e.g. `fields` or `limit_type`/`resets_at`
     */
    public function __construct(
        protected readonly string $errorCode,
        string $message,
        protected readonly int $status,
        protected readonly bool $recoverable = true,
        protected readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => array_merge([
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'recoverable' => $this->recoverable,
            ], $this->extra),
        ], $this->status);
    }
}

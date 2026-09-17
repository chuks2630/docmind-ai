<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * api-specification.md §3 POST /auth/register. Validation failures render as
 * the shared 422 validation_failed envelope via bootstrap/app.php's global
 * ValidationException handler — no per-controller formatting needed.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['apple', 'google', 'email'])],
            'provider_token' => ['required_if:provider,apple,google', 'string'],
            'email' => ['required_if:provider,email', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required_if:provider,email', 'nullable', 'string', 'min:8'],
            'guest_device_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}

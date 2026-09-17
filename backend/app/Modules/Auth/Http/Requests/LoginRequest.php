<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * api-specification.md §3 POST /auth/login — same shape as register, minus
 * guest_device_id, and no uniqueness check on email (we're matching, not creating).
 */
class LoginRequest extends FormRequest
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
            'email' => ['required_if:provider,email', 'nullable', 'email'],
            'password' => ['required_if:provider,email', 'nullable', 'string'],
        ];
    }
}

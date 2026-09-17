<?php

namespace App\Modules\Auth\Http\Resources;

use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `user` object nested in register/login responses (api-specification.md §3).
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'display_name' => $this->display_name,
            'email' => $this->email,
        ];
    }
}

<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Resources\UserProfileResource;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * api-specification.md §3 GET/DELETE /me.
 */
class MeController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function show(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user());
    }

    public function destroy(Request $request): Response
    {
        $this->auth->deleteAccount($request->user());

        return response()->noContent(202);
    }
}

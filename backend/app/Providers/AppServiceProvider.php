<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // api-specification.md's single-resource responses (e.g. GET /me) are flat
        // objects, not Laravel's default {"data": {...}} envelope. List endpoints
        // build their own {"data": [...], "next_cursor": ...} shape by hand instead
        // of relying on Resource collections, so disabling this globally is safe.
        JsonResource::withoutWrapping();
    }
}

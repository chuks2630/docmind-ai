<?php

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    AuthServiceProvider::class,
];

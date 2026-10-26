<?php

use App\Providers\AppServiceProvider;
use App\Providers\ArchiveServiceProvider;
use App\Providers\AuthViewsServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ArchiveServiceProvider::class,
    AuthViewsServiceProvider::class,
];

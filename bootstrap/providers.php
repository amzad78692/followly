<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ResponseMacroServiceProvider;
use App\Providers\VoltServiceProvider;
use Spatie\Glide\GlideServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ResponseMacroServiceProvider::class,
    VoltServiceProvider::class,
    GlideServiceProvider::class,
];

<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\VoterPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    VoterPanelProvider::class,
];

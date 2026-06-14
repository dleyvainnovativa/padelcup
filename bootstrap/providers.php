<?php

use App\Providers\AppServiceProvider;
use Laravel\Fortify\Features;

return [
    AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,  // <-- must be here

];

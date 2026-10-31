<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API entry point
|--------------------------------------------------------------------------
|
| This file does nothing but mount API versions. Each version owns a single
| file under routes/api/, which in turn loads its per-domain route files.
| Adding a v2 later means adding one line here — no existing route moves,
| and no client is disturbed.
|
*/

Route::prefix('v1')
    ->as('api-v1.')
    ->group(base_path('routes/api/v1.php'));

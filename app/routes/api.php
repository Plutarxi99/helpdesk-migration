<?php

use App\Http\Controllers\ApiHelpDeskController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->group(function () {
        Route::prefix('help-desks')->controller(ApiHelpDeskController::class)->group(
            function () {
                Route::get('/', 'getHelpDesks');
                Route::get('/requests', 'getRequests');
            }
        );
    }
);
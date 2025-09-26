<?php

use App\Http\Controllers\ApiHelpDeskController;
use App\Http\Controllers\ApiHelpDeskUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->group(function () {
        Route::prefix('help-desks')->controller(ApiHelpDeskController::class)->group(
            function () {
                Route::get('/', 'getHelpDesks');
                Route::get('/requests', 'getRequests');
                Route::get('/contacts', 'getContacts');
                Route::get('/answers', 'getAnswers');
                Route::get('/comments', 'getComments');
                Route::get('/departments', 'getDepartments');
                Route::get('/custom-fields', 'getCustomFields');
                Route::get('/custom-fields-option', 'getCustomFieldOptions');
            }
        );
        Route::prefix('help-desk-uploads')->controller(ApiHelpDeskUploadController::class)->group(
            function () {
                Route::get('/upload-requests', 'uploadRequests');
                Route::get('/upload-contacts', 'uploadContacts');
                Route::get('/upload-answers', 'uploadAnswers');
                Route::get('/upload-comments', 'uploadComments');
            }
        );
    }
);
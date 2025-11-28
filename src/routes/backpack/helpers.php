<?php

use Backpack\Helpers\app\Http\Controllers\FetchController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_filter([
        config('backpack.base.web_middleware', 'web'),
        config('backpack.base.middleware_key', 'admin'),
    ]),
], function () {
    Route::any('helpers/fetch/{key}', FetchController::class)->name('backpack.helpers.fetch');
});

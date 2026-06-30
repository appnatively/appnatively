<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\FormController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::group(
    'forms', function() {
        Route::get( '/', [FormController::class, 'index'] );
        Route::get( '/{id}', [FormController::class, 'show'] );
    }
);

Route::post( 'form', [FormController::class, 'store'] );

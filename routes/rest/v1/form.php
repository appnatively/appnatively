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

// Anyone may submit a form, but a signed-in caller must be recognised so the
// entry is attributed to them and the form plugin's own logged-in handling
// (nonces, user meta mapping) behaves as it would in a browser.
Route::post( 'form', [FormController::class, 'store'] )->middleware( 'user' );

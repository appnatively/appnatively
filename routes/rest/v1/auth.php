<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Auth\AuthController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::group(
    'auth', function() {
        // Public: these are how a caller obtains a token in the first place.
        Route::post( '/login', [AuthController::class, 'login'] );
        Route::post( '/register', [AuthController::class, 'register'] );
        Route::post( '/forgot-password', [AuthController::class, 'forgot_password'] );

        // Everything below acts on an existing account and requires the token
        // to be verified in permission_callback before the controller runs.
        Route::post( '/logout', [AuthController::class, 'logout'] )->middleware( 'auth' );
        Route::post( '/update-profile', [AuthController::class, 'update_profile'] )->middleware( 'auth' );
        Route::post( '/update-password', [AuthController::class, 'update_password'] )->middleware( 'auth' );
        Route::get( '/me', [AuthController::class, 'me'] )->middleware( 'auth' );
        Route::get( '/autologin-token', [AuthController::class, 'autologin_token'] )->middleware( 'auth' );
    }
);

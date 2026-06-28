<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Auth\AuthController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::group(
    'auth', function() {
        Route::post( '/login', [AuthController::class, 'login'] );
        Route::post( '/register', [AuthController::class, 'register'] );
        Route::post( '/logout', [AuthController::class, 'logout'] );
        Route::post( '/forgot-password', [AuthController::class, 'forgot_password'] );
        Route::post( '/update-profile', [AuthController::class, 'update_profile'] );
        Route::post( '/update-password', [AuthController::class, 'updatePassword'] );
        Route::get( '/me', [AuthController::class, 'me'] );
        Route::get( '/autologin-token', [AuthController::class, 'autologin_token'] );
    }
);

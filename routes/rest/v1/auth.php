<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\Auth\AuthController;
use AppNatively\WpMVC\Routing\Route;

Route::group(
    'auth', function() {
        Route::post( '/login', [AuthController::class, 'login'] );
        Route::post( '/register', [AuthController::class, 'register'] );
        Route::post( '/logout', [AuthController::class, 'logout'] );
        Route::post( '/forgot-password', [AuthController::class, 'forgotPassword'] );
        Route::post( '/update-profile', [AuthController::class, 'updateProfile'] );
        Route::post( '/update-password', [AuthController::class, 'updatePassword'] );
        Route::get( '/me', [AuthController::class, 'me'] );
    }
);

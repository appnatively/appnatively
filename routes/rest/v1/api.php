<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\ConnectionController;
use AppNatively\WpMVC\Routing\Route;

Route::get( 'connection/verify-token', [ ConnectionController::class, 'verify_token' ] );

Route::group(
    'connection', function () {
        Route::get( 'status', [ ConnectionController::class, 'get_status' ] );
        Route::delete( 'disconnect', [ ConnectionController::class, 'disconnect' ] );
        Route::post( 'init', [ ConnectionController::class, 'init_connect' ] );
    }
)->middleware( 'admin' );
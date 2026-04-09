<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\Ecommerce\ProductController;
use AppNatively\WpMVC\Routing\Route;

Route::group(
    'products', function() {
        Route::get( '/', [ProductController::class, 'index'] );
        Route::get( '/{id}', [ProductController::class, 'show'] );
    }
);

Route::group(
    'categories', function() {
        Route::get( '/', [ProductController::class, 'index'] );
        Route::get( '/{id}', [ProductController::class, 'show'] );
    }
);
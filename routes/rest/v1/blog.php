<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Blog\CategoryController;
use Crafium\AppNatively\App\Http\Controllers\Blog\PostController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::group(
    'posts', function() {
        Route::get( '/', [PostController::class, 'index'] );
        Route::get( '/{id}/related', [PostController::class, 'related'] );
        Route::get( '/{id}', [PostController::class, 'show'] );
    }
);

Route::group(
    'categories', function() {
        Route::get( '/', [CategoryController::class, 'index'] );
        Route::get( '/{id}', [CategoryController::class, 'show'] );
    }
);

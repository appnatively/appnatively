<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Ecommerce\CategoryController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\ProductController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\CartController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\OrderController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::group(
    'products', function() {
        Route::get( '/', [ProductController::class, 'index'] );
        Route::get( '/{id}', [ProductController::class, 'show'] );
    }
);

Route::group(
    'categories', function() {
        Route::get( '/', [CategoryController::class, 'index'] );
        Route::get( '/{id}', [CategoryController::class, 'show'] );
    }
);

Route::group(
    'cart', function() {
        Route::get( '/', [CartController::class, 'index'] );
        Route::post( '/add', [CartController::class, 'add'] );
        Route::post( '/update', [CartController::class, 'update'] );
        Route::post( '/remove', [CartController::class, 'remove'] );
        Route::post( '/clear', [CartController::class, 'clear'] );
    }
);

Route::get( 'orders', [OrderController::class, 'index'] );
Route::get( 'orders/{id}', [OrderController::class, 'show'] );
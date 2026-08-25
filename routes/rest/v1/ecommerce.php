<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Ecommerce\CategoryController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\ProductController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\CartController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\OrderController;
use Crafium\AppNatively\App\Http\Controllers\Ecommerce\WishlistController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::group(
    'products', function() {
        Route::get( '/', [ProductController::class, 'index'] );
        Route::get( '/filters', [ProductController::class, 'filters'] );
        Route::get( '/{id}', [ProductController::class, 'show'] );
    }
);

Route::group(
    'categories', function() {
        Route::get( '/', [CategoryController::class, 'index'] );
        Route::get( '/{id}', [CategoryController::class, 'show'] );
    }
);

// Carts serve guests and signed-in users alike, so a token is optional here —
// but when one is sent the cart has to bind to that account rather than to an
// anonymous session.
Route::group(
    'cart', function() {
        Route::get( '/', [CartController::class, 'index'] );
        Route::post( '/add', [CartController::class, 'add'] );
        Route::post( '/update', [CartController::class, 'update'] );
        Route::post( '/remove', [CartController::class, 'remove'] );
        Route::post( '/clear', [CartController::class, 'clear'] );
    }
)->middleware( 'user' );

// Orders are per-customer data: the token has to be verified before the
// controller runs, not inside it.
Route::get( 'orders', [OrderController::class, 'index'] )->middleware( 'auth' );
Route::get( 'orders/{id}', [OrderController::class, 'show'] )->middleware( 'auth' )->where( 'id', '\d+' );

Route::get( 'wishlist', [WishlistController::class, 'index'] );
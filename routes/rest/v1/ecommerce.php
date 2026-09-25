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
        // Builder-only: which taxonomies, attributes and custom fields exist to
        // build filter rows from. Names the site's custom fields, so it needs the
        // connection key like `plugins`.
        Route::get( '/filter-sources', [ProductController::class, 'filter_sources'] )->middleware( 'app' );
        Route::get( '/{id}/reviews', [ProductController::class, 'reviews'] );
        Route::get( '/{id}/related', [ProductController::class, 'related'] )->where( 'id', '\d+' );
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
        Route::post( '/discount', [CartController::class, 'discount'] );
        Route::post( '/discount/remove', [CartController::class, 'discount_remove'] );
    }
)->middleware( 'user' );

// Orders are per-customer data: the token has to be verified before the
// controller runs, not inside it.
Route::get( 'orders', [OrderController::class, 'index'] )->middleware( 'auth' );
// The detail endpoint also serves WooCommerce's guest order-received flow;
// each repository still enforces ownership or a valid guest order key.
Route::get( 'orders/{id}', [OrderController::class, 'show'] )->where( 'id', '\d+' );

Route::get( 'wishlist', [WishlistController::class, 'index'] );

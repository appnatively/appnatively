<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Directory\CategoryController;
use Crafium\AppNatively\App\Http\Controllers\Directory\ListingController;
use Crafium\AppNatively\App\Http\Controllers\Directory\TagController;
use Crafium\AppNatively\App\Http\Controllers\Directory\LocationController;
use Crafium\AppNatively\App\Http\Controllers\Directory\WishlistController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::get( 'categories', [CategoryController::class, 'index'] );
Route::get( 'categories/{id}', [CategoryController::class, 'show'] )->where( 'id', '\d+' );
Route::get( 'listings', [ListingController::class, 'index'] );
Route::get( 'listings/{id}/related', [ListingController::class, 'related'] )->where( 'id', '\d+' );
Route::get( 'listings/{id}/reviews', [ListingController::class, 'reviews'] )->where( 'id', '\d+' );
Route::get( 'listings/{id}', [ListingController::class, 'show'] )->where( 'id', '\d+' );
Route::get( 'tags', [TagController::class, 'index'] );
Route::get( 'locations', [LocationController::class, 'index'] );
Route::get( 'locations/{id}', [LocationController::class, 'show'] )->where( 'id', '\d+' );
Route::get( 'wishlist', [WishlistController::class, 'index'] );

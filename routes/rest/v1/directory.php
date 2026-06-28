<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Directory\CategoryController;
use Crafium\AppNatively\App\Http\Controllers\Directory\ListingController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::get( 'categories', [CategoryController::class, 'index'] );
Route::get( 'listings', [ListingController::class, 'index'] );

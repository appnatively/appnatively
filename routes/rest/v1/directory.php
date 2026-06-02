<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\Directory\CategoryController;
use AppNatively\App\Http\Controllers\Directory\ListingController;
use AppNatively\WpMVC\Routing\Route;

Route::get( 'categories', [CategoryController::class, 'index'] );
Route::get( 'listings', [ListingController::class, 'index'] );

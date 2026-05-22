<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\Directory\ListingController;
use AppNatively\WpMVC\Routing\Route;

Route::get( 'listings', [ListingController::class, 'index'] );

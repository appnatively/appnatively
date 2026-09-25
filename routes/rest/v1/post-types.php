<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\PostType\TermController;
use Crafium\AppNatively\App\Http\Controllers\PostType\PostController;
use Crafium\AppNatively\App\Http\Controllers\PostType\PostTypeController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::get( '/', [PostTypeController::class, 'index'] );

/**
 * The site's post types, taxonomies and custom fields, and which of them each
 * app may read. Key-protected: it describes the content model, and writing it
 * widens what the public post type endpoints serve.
 */
Route::get( 'available', [PostTypeController::class, 'available'] )->middleware( 'app' );
Route::post( 'selection', [PostTypeController::class, 'store'] )->middleware( 'app' );

Route::group(
    'posts', function() {
        Route::get( '/', [PostController::class, 'index'] );
        Route::get( '/{id}/related', [PostController::class, 'related'] );
        Route::get( '/{id}', [PostController::class, 'show'] );
    }
);

Route::group(
    'terms', function() {
        Route::get( '/', [TermController::class, 'index'] );
        Route::get( '/{id}', [TermController::class, 'show'] );
    }
);

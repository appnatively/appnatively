<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\ShopController;
use Crafium\AppNatively\App\Http\Controllers\ConnectionController;
use Crafium\AppNatively\App\Http\Controllers\PluginsController;
use Crafium\AppNatively\WpMVC\Routing\Route;

/**
 * Unauthenticated handshake. Confirms the plugin is installed and reachable
 * and reports its version — nothing about the site's configuration.
 */
Route::get( 'connection', [ConnectionController::class, 'index'] );

/**
 * Describes which supported plugins are active. This enumerates installed
 * software, so it is restricted to callers holding the site connection key
 * rather than served to anyone who asks.
 */
Route::get( 'plugins', [PluginsController::class, 'index'] )->middleware( 'app' );

Route::get( 'shop', [ShopController::class, 'index'] );

Route::group(
    'ecommerce', function() {
        require __DIR__ . '/ecommerce.php';
    }
);

Route::group(
    'directory', function() {
        require __DIR__ . '/directory.php';
    }
);

Route::group(
    'blog', function() {
        require __DIR__ . '/blog.php';
    }
);

require __DIR__ . '/form.php';
require __DIR__ . '/auth.php';

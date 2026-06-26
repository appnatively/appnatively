<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\PluginsController;
use AppNatively\WpMVC\Routing\Route;

Route::get( 'plugins', [PluginsController::class, 'index'] );

Route::group(
    'ecommerce', function() {
        require __DIR__ . '/ecommerce.php';
    } 
);

require __DIR__ . '/form.php';
require __DIR__ . '/auth.php';
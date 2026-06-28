<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\PluginsController;
use Crafium\AppNatively\WpMVC\Routing\Route;
use Crafium\AppNatively\WpMVC\Routing\Response;

Route::get(
    'me', function(){
        return Response::send( [] );
    }
);

Route::get( 'plugins', [PluginsController::class, 'index'] );
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

require __DIR__ . '/form.php';
require __DIR__ . '/auth.php';

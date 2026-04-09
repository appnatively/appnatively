<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Routing\Route;

Route::group( 'ecommerce', function() {
    require __DIR__ . '/ecommerce.php';
} );
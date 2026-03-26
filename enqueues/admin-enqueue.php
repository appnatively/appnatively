<?php

use AppNatively\WpMVC\Enqueue\Enqueue;

defined( 'ABSPATH' ) || exit;

if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ) {
    Enqueue::script( 'appnatively-runtime', 'build/runtime' );
}

Enqueue::script( 'appnatively-script', 'build/js/app' );
Enqueue::style( 'appnatively-style', 'build/css/app' );

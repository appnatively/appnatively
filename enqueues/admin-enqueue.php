<?php

use AppNatively\WpMVC\Enqueue\Enqueue;

defined( 'ABSPATH' ) || exit;

if ( 'toplevel_page_appnatively' !== $hook_suffix ) {
    return;
}

if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ) {
    Enqueue::script( 'appnatively-runtime', 'build/runtime' );
}

wp_enqueue_style( 'wp-components' );
Enqueue::script( 'appnatively-script', 'build/js/app' );
Enqueue::style( 'appnatively-style', 'build/css/app' );

wp_localize_script(
    'appnatively-script', 'appnativelyData', [
        'restUrl' => get_rest_url( null, 'appnatively/v1' ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'siteUrl' => get_site_url(),
    ]
);
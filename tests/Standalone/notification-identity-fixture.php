<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

/** Cross-language proof fixture: only synthetic state; no site bootstrap or local credentials. */
require __DIR__ . '/notification-fixture.php';
use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
Events::save_binding( fixture_binding() );
$provider = new \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
$provider->routes();
$token   = Auth::issue( 7 );
$request = new WP_REST_Request(
    json_decode( stream_get_contents( STDIN ), true ), [
        'Authorization' => 'Bearer ' . $token,
    ]
);
if ( $GLOBALS['routes']['/notifications/identity']['permission_callback']( $request ) !== true ) {
    exit( 2 );
}
$result = $provider->identity( $request );
if ( $result instanceof WP_Error ) {
    echo json_encode(
        [
            'error' => $result->code,
        ]
    );
    exit( 1 );
}
echo json_encode( $result->data );

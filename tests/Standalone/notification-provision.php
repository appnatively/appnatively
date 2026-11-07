<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

require __DIR__ . '/notification-fixture.php';
require dirname( __DIR__, 2 ) . '/app/Http/Middleware/EnsureIsConnectedApp.php';
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
$provider = new Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
$provider->routes();
$route   = $routes['/notifications/pair'];
$payload = [
    'appId'              => 'app',
    'sourceId'           => 'source',
    'operationId'        => 'job',
    'connectionRevision' => 4,
    'generation'         => 1,
    'ticket'             => str_repeat( 'a', 64 ),
    'expiresAt'          => ( time() + 900 ) * 1000,
    'endpoint'           => 'https://backend.example.test/api/notification-sources',
    'keys'               => [],
];
foreach ( [
    'events',
    'identity',
    'subjects',
] as $scope ) {
    $payload['keys'][$scope] = [
        'id'     => $scope,
        'secret' => str_repeat( 'b', 64 ),
    ];
}
$checks = 0;

function check( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

function owner( $payload, $key = 'owner-key' ) {
    return new WP_REST_Request(
        $payload, [
            'Authorization' => 'Bearer ' . $key,
        ]
    );
}

check( $route['permission_callback']( owner( $payload ) ) === true, 'Owner key authenticates' );
check( $route['permission_callback']( owner( $payload, 'mobile-token' ) ) instanceof WP_Error, 'Mobile token cannot provision' );
$consent = get_option( 'craf_appna_notification_consent' );
delete_option( 'craf_appna_notification_consent' );
check( $route['callback']( owner( $payload ) ) instanceof WP_Error && ! $requests, 'No pairing HTTP without separate acknowledgement' );
update_option( 'craf_appna_notification_consent', $consent );
$reply_body = '{"paired":true}';
$result     = $route['callback']( owner( $payload ) );
check(
    $result->data === [
        'accepted' => true,
        'status'   => 'ready',
    ], 'Signed pairing completes within owner request'
);
check( count( $requests ) === 1 && ! $loopbacks, 'Pairing does not dispatch queue work' );
$route['callback']( owner( $payload ) );
check( count( $requests ) === 1, 'Repeated completed operation is idempotent' );
Events::emit(
    'custom.created', [], [
        'id' => 'before-repair',
    ]
);
$repair               = $payload;
$repair['ticket']     = str_repeat( 'c', 64 );
$repair['expiresAt'] += 1000;
check( $route['callback']( owner( $repair ) )->data['status'] === 'ready' && count( queued_items() ) === 1, 'Same-connection repair preserves queue' );
$stale                       = $payload;
$stale['connectionRevision'] = 3;
check( $route['callback']( owner( $stale ) ) instanceof WP_Error, 'Older revision rejected' );
$unsafe             = $payload;
$unsafe['endpoint'] = 'http://localhost/notification-sources';
check( $route['callback']( owner( $unsafe ) ) instanceof WP_Error, 'Unsafe external callback rejected' );
$rotate                       = $payload;
$rotate['connectionRevision'] = 5;
$rotate['ticket']             = str_repeat( 'd', 64 );
check( $route['callback']( owner( $rotate ) )->data['status'] === 'ready' && ! queued_items(), 'Connection revision switch discards old work' );
$reply_body                   = '{}';
$rotate['connectionRevision'] = 6;
$rotate['ticket']             = str_repeat( 'e', 64 );
check( $route['callback']( owner( $rotate ) ) instanceof WP_Error, 'Invalid signed acknowledgement returns failure' );
check( Events::bindings()['source']['enabled'] === false, 'Failed pairing does not enable delivery' );
echo "PASS: {$checks} manual pairing checks (offline)\n";

<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

require __DIR__ . '/notification-fixture.php';
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
Events::save_binding( fixture_binding() );
update_user_meta(
    7, 'capabilities', [
        'customer' => true,
    ]
);
( new \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider() )->routes();
$route  = $GLOBALS['routes']['/notifications/subjects'];
$checks = 0;

function check( $value, $message ) {
    if ( ! $value ) {
        throw new RuntimeException( $message );
    }
    $GLOBALS['checks']++;
}

function request( $params, $valid = true ) {
    $time                                  = (string) time();
    $r                                     = new WP_REST_Request(
        $params, [
            'X-AppNatively-Source-Id' => 'source',
            'X-AppNatively-Key-Id'    => 'subjects',
            'X-AppNatively-Timestamp' => $time,
        ]
    );
    $r->headers['X-AppNatively-Signature'] = $valid ? base64_encode( hash_hmac( 'sha256', $time . '.' . $r->get_body(), str_repeat( 's', 64 ), true ) ) : 'invalid';
    return $r;
}

$r = request(
    [
        'ids'      => [
            '7',
            '7',
            '999',
        ],
        'profiles' => true,
    ]
);
check( $route['permission_callback']( $r ) === true, 'Signed lookup authenticates' );
$response = $route['callback']( $r );
check( count( $response->data['subjects'] ) === 1, 'Deduplicate IDs and omit missing users' );
check(
    $response->data['subjects'][0]['profile'] === [
        'displayName' => 'Fixture Customer',
        'email'       => 'fixture@example.test',
    ], 'Display-only profile projection'
);
check(
    $response->data['subjects'][0]['attributes'] === [
        'roles' => [
            'customer',
        ],
    ], 'Roles remain typed'
);
check( $response->headers['Cache-Control'] === 'private, no-store, max-age=0', 'No shared cache' );
$ordinary = $route['callback'](
    request(
        [
            'ids' => [
                '7',
            ],
        ]
    )
);
check( ! isset( $ordinary->data['subjects'][0]['profile'] ), 'Profiles are opt in' );
check(
    $route['permission_callback'](
        request(
            [
                'ids' => [
                    '7',
                ],
            ], false
        )
    ) !== true, 'Bad signature rejected'
);
echo "PASS: {$checks} WordPress audience profile checks (offline)\n";

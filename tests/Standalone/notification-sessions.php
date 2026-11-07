<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

require __DIR__ . '/notification-fixture.php';
use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
$checks = 0;

function check( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

Events::save_binding( fixture_binding() );
$provider = new Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
$provider->boot();
$provider->routes();
$before         = $wpdb->get_results( 'SELECT * FROM usermeta', ARRAY_A );
$cookie_session = [
    'login'      => time() - 10,
    'expiration' => time() + 3600,
];
$value          = [
    'appId'          => 'app',
    'sourceId'       => 'source',
    'generation'     => 1,
    'installationId' => 'device',
    'challenge'      => str_repeat( 'a', 64 ),
];
$response       = $provider->identity( new WP_REST_Request( $value ) );
$session        = json_decode( $response->data['sessionProof']['assertion'], true );
check( $session['sessionIssuedAt'] === $cookie_session['login'] * 1000 && $session['sessionExpiresAt'] === $cookie_session['expiration'] * 1000, 'Proof uses native cookie lifetime' );
check( $before === $wpdb->get_results( 'SELECT * FROM usermeta', ARRAY_A ), 'Cookie proof never writes user metadata' );
check( ! isset( $hooks['set_logged_in_cookie'] ), 'Does not intercept native cookie creation' );
$secret = str_repeat( 'a', 64 );
$legacy = '7.' . $secret;
$hash   = hash( 'sha256', $secret );
update_user_meta(
    7, Auth::META_KEY, [
        $hash => [
            'issued'  => time() - 100,
            'expires' => time() + 3600,
        ],
    ]
);
$request = new WP_REST_Request(
    $value, [
        'Authorization' => 'Bearer ' . $legacy,
    ]
);
check( $routes['/notifications/identity']['permission_callback']( $request ) === true, 'Legacy bearer proves identity' );
$proof     = $provider->identity( $request )->data;
$assertion = json_decode( $proof['sessionProof']['assertion'], true );
check( $assertion['sessionRef'] === Events::app_session_ref( 7, $hash ), 'Proof reference can be derived from stored pre-upgrade token hash' );
$metadata = get_user_meta( 7, Auth::META_KEY, true )[$hash];
check(
    array_keys( $metadata ) === [
        'issued',
        'expires',
    ], 'No notification fields added to app tokens'
);
check( ! isset( $assertion['expiresAt'] ) && $assertion['sessionIssuedAt'] === $metadata['issued'] * 1000, 'Existing strict session-proof contract retained' );
check( $proof['sessionProof']['signature'] === base64_encode( hash_hmac( 'sha256', $proof['sessionProof']['assertion'], str_repeat( 's', 64 ), true ) ), 'Proof signature matches exact assertion' );
for ( $i = 0; $i < 10; ++$i ) {
    Auth::issue( 7 );
}
$items       = queued_items( 'source', 'subjects' );
$revocations = array_values(
    array_filter(
        $items, function ( $job ) {
            return $job['event']['type'] === 'system.session.revoked';
        }
    )
);
check( count( $revocations ) === 1 && $revocations[0]['event']['data']['sessionRef'] === $assertion['sessionRef'], 'Legacy eviction queues exactly one matching revocation' );
check( Auth::resolve( $request ) === null, 'Original app-token cap still revokes the evicted token' );
$all = Auth::count_active( 7 );
Auth::revoke_everyones();
check( $all === 10 && Auth::count_active( 7 ) === 0, 'Existing bulk app logout still clears all tokens' );
$items       = queued_items( 'source', 'subjects' );
$revocations = array_filter(
    $items, function ( $job ) {
        return $job['event']['type'] === 'system.session.revoked';
    }
);
check( count( $revocations ) === 11, 'Bulk app logout queues each removed session without polling' );
Events::queue( 'source', 'subjects' )->discard();
$cookie_hash = hash( 'sha256', 'fixture-cookie' );
update_user_meta(
    7, 'session_tokens', [
        $cookie_hash => [
            'login'      => time() - 10,
            'expiration' => time() + 3600,
        ],
    ]
);
update_user_meta(
    8, 'session_tokens', [
        hash( 'sha256', 'other-cookie' ) => [
            'expiration' => time() + 3600,
        ],
    ]
);
$rows = $wpdb->get_results( "SELECT umeta_id FROM usermeta WHERE meta_key='session_tokens'", ARRAY_A );
$ids  = array_column( $rows, 'umeta_id' );
do_action( 'delete_user_meta', $ids, 0, 'session_tokens' );
$wpdb->query( "DELETE FROM usermeta WHERE meta_key='session_tokens'" );
do_action( 'deleted_user_meta', $ids, 0, 'session_tokens' );
$items = queued_items( 'source', 'subjects' );
check( count( $items ) === 2, 'Global WordPress session reset covers all affected user IDs' );
check( $items[0]['event']['data']['sessionRef'] === Events::session_ref( 'fixture-cookie' ), 'Cookie proof and revocation use the same reference' );
check(
    array_column( array_column( $items, 'event' ), 'entity' ) === [
        [
            'type' => 'user',
            'id'   => '7',
        ],
        [
            'type' => 'user',
            'id'   => '8',
        ],
    ], 'Global reset does not emit for user zero'
);
run_queue( 'source', 'subjects' );
check( count( $requests ) === 2, 'Revocation jobs verify removed native sessions and send' );
Events::queue( 'source', 'subjects' )->discard();
$wpdb->fail = 'INSERT INTO options';
$token      = Auth::issue( 7 );
Auth::revoke( $token );
$wpdb->fail = '';
check(
    Auth::resolve(
        new WP_REST_Request(
            [], [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
    ) === null, 'Queue failure never rolls back native app logout'
);
$token   = Auth::issue( 7 );
$before  = get_user_meta( 7, Auth::META_KEY, true );
$request = new WP_REST_Request(
    $value, [
        'Authorization' => 'Bearer ' . $token,
    ]
);
Events::queue( 'source', 'subjects' )->discard();
$wpdb->query( 'BEGIN' );
Auth::revoke( $token );
$wpdb->query( 'ROLLBACK' );
// Clear this fixture's option cache just as a new queue worker request would.
foreach ( array_keys( $options ) as $key ) {
    if ( strpos( $key, 'craf_appna_nq_' ) === 0 ) {
        unset( $options[$key] );
    }
}
check( get_user_meta( 7, Auth::META_KEY, true ) === $before, 'Caller rollback is preserved without transaction probes or implicit commits' );
check( ! queued_items( 'source', 'subjects' ), 'Rolled-back hook did not commit separate work' );
$cookie_session['expiration'] = time();
check( $provider->identity( new WP_REST_Request( $value ) ) instanceof WP_Error, 'Notification proof rejects exact cookie expiry without modifying login state' );
echo "PASS: {$checks} native session isolation checks (offline)\n";

<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

/** Native WordPress + real WpMVC Queue against an explicitly disposable MySQL socket. */
if ( PHP_SAPI !== 'cli' ) {
    exit;
}
$socket = $argv[1] ?? '';
if ( ! preg_match( '#^/private/tmp/appna-native-tests-[a-z0-9_]+/mysql\.sock$#', $socket ) ) {
    fwrite( STDERR, "Pass the disposable /private/tmp/appna-native-tests-*/mysql.sock path.\n" );
    exit( 2 );
}
$database = 'appna_notifications_test';
// phpcs:ignore WordPress.DB.RestrictedClasses -- Connect only to the guarded disposable test server before WordPress is loaded.
$connection = new mysqli( 'localhost', 'root', '', '', 0, $socket );
if ( $connection->connect_error ) {
    throw new RuntimeException( 'Disposable database connection unavailable.' );
}
if ( ( $argv[2] ?? '' ) === '--shutdown' ) {
    $connection->query( 'SHUTDOWN' );
    echo "Stopped disposable MySQL instance.\n";
    exit;
}
$connection->query( 'DROP DATABASE IF EXISTS appna_notifications_test' );
$connection->query( 'CREATE DATABASE appna_notifications_test' );
$appna_test_plugin_dir = dirname( __DIR__, 2 );
$core                  = dirname( __DIR__, 5 ) . '/';
$scratch               = dirname( $socket ) . '/wordpress-content';
@mkdir( $scratch . '/mu-plugins', 0777, true );
@mkdir( $scratch . '/plugins', 0777, true );
@mkdir( $scratch . '/themes', 0777, true );
define( 'ABSPATH', $core );
define( 'DB_NAME', $database );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost:' . $socket );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_CONTENT_DIR', $scratch );
define( 'WP_CONTENT_URL', 'https://appna.example.test/wp-content' );
define( 'WP_PLUGIN_DIR', $scratch . '/plugins' );
define( 'WPMU_PLUGIN_DIR', $scratch . '/mu-plugins' );
define( 'WP_HOME', 'https://appna.example.test' );
define( 'WP_SITEURL', 'https://appna.example.test' );
define( 'WP_INSTALLING', true );
define( 'WP_USE_THEMES', false );
define( 'DISABLE_WP_CRON', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', dirname( $socket ) . '/wordpress-debug.log' );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
foreach ( [
    'AUTH_KEY',
    'SECURE_AUTH_KEY',
    'LOGGED_IN_KEY',
    'NONCE_KEY',
    'AUTH_SALT',
    'SECURE_AUTH_SALT',
    'LOGGED_IN_SALT',
    'NONCE_SALT',
] as $key ) {
    define( $key, 'synthetic-notification-test-' . $key );
}
$GLOBALS['table_prefix'] = 'appna_test_';
$_SERVER['HTTP_HOST']    = 'appna.example.test';
$_SERVER['SERVER_NAME']  = 'appna.example.test';
$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
$_SERVER['SERVER_PORT']  = '443';
$_SERVER['HTTPS']        = 'on';
$_SERVER['REQUEST_URI']  = '/';
// Never load wp-load.php or wp-config.php. Only installed core source is used.
require_once ABSPATH . 'wp-includes/plugin.php';
$http = [];
add_filter(
    'pre_http_request', function ( $pre, $args, $url ) use ( &$http ) {
        if ( strpos( $url, '/api/notification-sources/' ) !== false ) {
            $http[] = [
                $url,
                $args,
            ];
            return [
                'headers'  => [],
                'body'     => '{}',
                'response' => [
                    'code'    => 202,
                    'message' => 'Accepted',
                ],
                'cookies'  => [],
            ];
        }
        if ( strpos( $url, 'admin-ajax.php' ) !== false ) {
            return [
                'headers'  => [],
                'body'     => '',
                'response' => [
                    'code'    => 200,
                    'message' => 'OK',
                ],
                'cookies'  => [],
            ];
        }
        return new WP_Error( 'isolated_test', 'External networking disabled in native tests.' );
    }, 10, 3
);
file_put_contents( WPMU_PLUGIN_DIR . '/appna.php', "<?php\nrequire " . var_export( $appna_test_plugin_dir . '/appnatively.php', true ) . ";\n" );
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
wp_install( 'Notification isolation test', 'notification-test-admin', 'fixture@example.test', false, '', 'test-only-password-4581' );
$checks = 0;

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

use Crafium\AppNatively\App\Support\NotificationEvents as Events;
use Crafium\AppNatively\App\Support\Auth;
$provider = new Crafium\AppNatively\App\Providers\NotificationEventServiceProvider( Crafium\AppNatively\WpMVC\App::instance() );
// The plugin provider is already booted by native plugins_loaded.
Events::install();
$keys = [];
foreach ( [
    'events',
    'identity',
    'subjects',
] as $scope ) {
    $keys[$scope] = [
        'id'     => $scope,
        'secret' => str_repeat( 's', 64 ),
    ];
}
$binding = [
    'sourceId'     => 'native',
    'appId'        => 'app',
    'generation'   => 1,
    'enabled'      => true,
    'provisioning' => 'ready',
    'endpoint'     => 'https://backend.example.test/api/notification-sources',
    'keys'         => $keys,
];
check( Events::save_binding( $binding, Events::bindings()['native'] ?? null ), 'Binding saved using native wpdb' );
foreach ( [
    'craf_appna_event_bindings',
    'craf_appna_event_sequences'
] as $table ) {
    $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->prefix . $table ), ARRAY_A );
    check( $status['Engine'] === 'InnoDB', 'Schema builder retains the owned table engine' );
}
$stored_payload = $wpdb->get_var( $wpdb->prepare( 'SELECT payload FROM %i WHERE source_id=%s', Events::bindings_table(), 'native' ) );
$payload_column = $wpdb->get_row( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', Events::bindings_table(), 'payload' ), ARRAY_A );
check( $payload_column['Null'] === 'NO', 'Schema builder retains the encrypted payload NOT NULL constraint' );
delete_option( 'craf_appna_events_schema' );
Events::install();
check( $wpdb->get_var( $wpdb->prepare( 'SELECT payload FROM %i WHERE source_id=%s', Events::bindings_table(), 'native' ) ) === $stored_payload, 'Repeated schema synchronization preserves encrypted bindings' );
delete_option( 'craf_appna_notification_epoch' );
check( get_option( 'craf_appna_notifications_enabled', 'yes' ) === 'yes' && Events::emit( 'custom.created', [], ['id' => 'default-on'] ) === 'default-on', 'Default On captures paired events without separate sharing settings' );
Events::queue( 'native', 'events' )->discard();
update_option( 'craf_appna_notification_epoch', 'native-test' );

function jobs( $channel ) {
    $items = [];
    foreach ( Events::queue( 'native', $channel )->get_batches() as $batch ) {
        $items = array_merge( $items, $batch->data );
    }
    foreach ( $items as &$item ) {
        $item['event'] = json_decode( $item['event'], true );
    }
    unset( $item );
    return $items;
}

function work( $channel ) {
    $q = Events::queue( 'native', $channel );
    add_filter( $q->get_identifier() . '_wp_die', '__return_false' );
    $m = new ReflectionMethod( $q, 'handle' );
    $m->setAccessible( true );
    if ( $q->is_queued() && ! $q->is_paused() ) {
        $m->invoke( $q );
    }
}

$event_queue   = Events::queue( 'native', 'events' );
$subject_queue = Events::queue( 'native', 'subjects' );
$event_queue->discard();
$subject_queue->discard();
$appna_profile_start = $wpdb->num_queries;
$wpdb->insert_id     = 4321;
$wpdb->last_error    = 'native-state';
$wpdb->last_query    = 'native-query';
Events::emit(
    'custom.created', [], [
        'id' => 'native-signed',
    ]
);
$appna_profiles['hook_enqueue'] = $wpdb->num_queries - $appna_profile_start;
check( $wpdb->insert_id === 4321 && $wpdb->last_error === 'native-state' && $wpdb->last_query === 'native-query', 'Native wpdb diagnostics and insert ID preserved' );
$batch    = $event_queue->get_batches()[0];
$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name=%s", $batch->key ) );
check(
    in_array(
        $autoload, [
            'no',
            'off',
            'auto-off',
        ], true
    ), 'Queue batches never autoload into native front-end requests'
);
check( count( jobs( 'events' ) ) === 1 && ! $http, 'Native hook saved only a framework batch' );
work( 'events' );
check( count( $http ) === 1 && ! $event_queue->is_queued(), 'Real queue processed native options batch' );
$http = [];
$user = wp_create_user( 'notification-user', 'test-only-password-4582', 'user@example.test' );
check( ! is_wp_error( $user ), 'Native user created' );
wp_set_current_user( $user );
$subject_queue->discard();
$native_meta               = get_user_meta( $user );
$token                     = WP_Session_Tokens::get_instance( $user )->create( time() + 3600 );
$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $token );
$before                    = get_user_meta( $user );
$request                   = new WP_REST_Request( 'POST', '/craf_appna/v1/notifications/identity' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body(
    wp_json_encode(
        [
            'sourceId'       => 'native',
            'appId'          => 'app',
            'generation'     => 1,
            'installationId' => 'device',
            'challenge'      => str_repeat( 'a', 64 ),
        ]
    )
);
$appna_profile_start        = $wpdb->num_queries;
$response                   = $provider->identity( $request );
$appna_profiles['identity'] = $wpdb->num_queries - $appna_profile_start;
check( $response instanceof WP_REST_Response, 'Native cookie mints notification proof' );
check( get_user_meta( $user ) === $before, 'Identity endpoint never adds cookie metadata' );
check( ! get_user_meta( $user, '_craf_appna_cookie_sessions', true ) && ! get_user_meta( $user, '_craf_appna_session_cutoff', true ), 'No experimental cookie-session metadata' );
$subject_queue->discard();
$secret = str_repeat( 'a', 64 );
$hash   = hash( 'sha256', $secret );
$legacy = $user . '.' . $secret;
update_user_meta(
    $user, Auth::META_KEY, [
        $hash => [
            'issued'  => time() - 100,
            'expires' => time() + 3600,
        ],
    ]
);
for ( $i = 0; $i < 10; ++$i ) {
    Auth::issue( $user );
}
$revoked = array_values(
    array_filter(
        jobs( 'subjects' ), function ( $job ) {
            return $job['event']['type'] === 'system.session.revoked';
        }
    )
);
check( count( $revoked ) === 1 && $revoked[0]['event']['data']['sessionRef'] === Events::session_ref( $legacy ), 'Pre-upgrade app-token eviction queues matching revocation' );
Auth::revoke_everyones();
check( Auth::count_all_active() === 0, 'Native bulk app logout preserved' );
$subject_queue->discard();
$user2 = wp_create_user( 'notification-user-2', 'test-only-password-4583', 'user2@example.test' );
WP_Session_Tokens::get_instance( $user2 )->create( time() + 3600 );
$subject_queue->discard();
WP_Session_Tokens::destroy_all_for_all_users();
$jobs = jobs( 'subjects' );
check( count( $jobs ) === 2, 'Native global WordPress logout queues every cookie session' );
check( ! in_array( '0', array_column( array_column( array_column( $jobs, 'event' ), 'entity' ), 'id' ), true ), 'Global logout never addresses user zero' );
work( 'subjects' );
check( count( $http ) === 2, 'Global revocations validate removal and deliver' );
$http = [];
$subject_queue->discard();
$u = new WP_User( $user );
$u->set_role( 'subscriber' );
$subject_queue->discard();
$u->set_role( 'editor' );
check( count( jobs( 'subjects' ) ) === 1, 'Native role replacement emits one snapshot' );
$u->set_role( 'author' );
$http = [];
work( 'subjects' );
foreach ( $http as $sent ) {
    $e = json_decode( $sent[1]['body'], true );
    if ( $e['type'] === 'system.subject.updated' ) {
        check(
            $e['data']['attributes']['roles'] === [
                'author',
            ], 'Delayed account jobs read current native roles'
        );
    }
}
$http = [];
$event_queue->discard();
register_post_type(
    'hp_listing', [
        'public' => true,
    ]
);
$post = wp_insert_post(
    [
        'post_type'   => 'hp_listing',
        'post_status' => 'pending',
        'post_title'  => 'Fixture listing',
        'post_author' => $user,
    ]
);
check( ! get_post_meta( $post, '_craf_appna_event_version', true ), 'Listing event writes no native version metadata' );
$meta = get_post_meta( $post );
wp_update_post(
    [
        'ID'          => $post,
        'post_status' => 'publish',
    ]
);
check( get_post_meta( $post ) === $meta, 'Native listing transition preserves all post metadata' );
$event_queue->discard();
// Deletion is captured once, after WordPress has removed the account.
require_once ABSPATH . 'wp-admin/includes/user.php';
$deleted_user = wp_create_user( 'notification-delete-user', 'test-only-password-4584', 'deleted@example.test' );
$subject_queue->discard();
check( wp_delete_user( $deleted_user ) && ! get_userdata( $deleted_user ), 'Native account deletion completes' );
$deleted_jobs = jobs( 'subjects' );
check( count( $deleted_jobs ) === 1 && $deleted_jobs[0]['event']['type'] === 'system.subject.deleted', 'Completed deletion queues exactly one account event' );
$subject_queue->discard();
// A missing plugin-owned table must not expose database errors or block app login.
$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', Events::bindings_table() ) );
$wpdb->insert_id  = 9876;
$wpdb->last_error = 'caller-diagnostic';
$wpdb->last_query = 'caller-query';
check( Events::emit( 'custom.created', [] ) === null && $wpdb->insert_id === 9876 && $wpdb->last_error === 'caller-diagnostic' && $wpdb->last_query === 'caller-query', 'Unavailable notification storage preserves caller state' );
$unavailable_token   = Auth::issue( $user );
$unavailable_request = new WP_REST_Request();
$unavailable_request->set_header( 'Authorization', 'Bearer ' . $unavailable_token );
check( Auth::resolve( $unavailable_request ) === $user, 'Native app authentication survives unavailable notification storage' );
Events::install();
check( Events::save_binding( $binding ), 'Owner setup repairs disposable notification storage' );
// Queue option writes participate in a caller transaction, and never commit it.
$wpdb->query( 'START TRANSACTION' );
Events::emit(
    'custom.created', [], [
        'id' => 'rolled-back',
    ]
);
$wpdb->query( 'ROLLBACK' );
check( ! jobs( 'events' ), 'Native caller rollback was not implicitly committed' );
// MyISAM on native user metadata must not gate login or app auth.
$wpdb->query( "ALTER TABLE {$wpdb->usermeta} ENGINE=MyISAM" );
$app_token    = Auth::issue( $user );
$auth_request = new WP_REST_Request();
$auth_request->set_header( 'Authorization', 'Bearer ' . $app_token );
check( Auth::resolve( $auth_request ) === $user, 'App authentication works with native MyISAM metadata' );
Auth::revoke( $app_token );
check( Auth::resolve( $auth_request ) === null, 'App logout is independent of native table engine' );
$wpdb->query( "ALTER TABLE {$wpdb->usermeta} ENGINE=InnoDB" );
$event_queue->discard();
$subject_queue->discard();
Events::emit(
    'custom.created', [], [
        'id' => 'withdraw',
    ]
);
update_option( 'craf_appna_notifications_enabled', 'no' );
$event_queue->handle_cron_healthcheck();
check( ! $event_queue->is_queued(), 'Sending Off clears native queue work' );
check( ! $http, 'No additional external request after sending Off' );
$event_queue->handle_cron_healthcheck();
check( true, 'Idle queue health check returns to other cron work' );
// Real parallel connections must not allocate the same transition version.
$entity  = random_int( 100000, 999999999 );
$workers = [];
for ( $i = 0; $i < 4; ++$i ) {
    $pipes   = [];
    $process = proc_open(
        [
            PHP_BINARY,
            __DIR__ . '/notification-sequence-worker.php',
        ], [
            0 => [
                'pipe',
                'r',
            ],
            1 => [
                'pipe',
                'w',
            ],
            2 => [
                'pipe',
                'w',
            ],
        ], $pipes
    );
    if ( ! is_resource( $process ) ) {
        throw new RuntimeException( 'Cannot start isolated test process' );
    }
    fwrite(
        $pipes[0], json_encode(
            [
                'host'     => DB_HOST,
                'database' => DB_NAME,
                'user'     => DB_USER,
                'password' => DB_PASSWORD,
                'prefix'   => $wpdb->prefix,
                'entityId' => $entity,
            ]
        )
    );
    fclose( $pipes[0] );
    $workers[] = [
        $process,
        $pipes,
    ];
}
$versions = [];
foreach ( $workers as [$process, $pipes] ) {
    $output = stream_get_contents( $pipes[1] );
    $error  = stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    check( proc_close( $process ) === 0, 'Concurrent sequence worker completes: ' . $error );
    $versions = array_merge( $versions, json_decode( $output, true ) );
}
sort( $versions );
check( $versions === range( 501, 600 ), 'One hundred concurrent transition versions are unique' );
// One native setting controls delivery, defaulting to On without a separate save.
wp_set_current_user( 1 );
delete_option( 'craf_appna_notifications_enabled' );
delete_option( 'craf_appna_notification_epoch' );
ob_start();
$provider->settings_page();
$html = ob_get_clean();
check( strpos( $html, 'name="craf_appna_notifications_enabled" value="1"  checked=' ) !== false, 'Native settings default sending to On' );
check( substr_count( $html, 'type="checkbox"' ) === 1, 'Native settings show only the sending switch' );
check( get_option( 'craf_appna_notification_epoch', null ) === null && Events::allowed(), 'Default sending is active without saved notification setup' );
$_POST = [
    'craf_appna_save_notifications'    => '1',
    'craf_appna_notifications_enabled' => '1',
    '_wpnonce'                         => wp_create_nonce( 'craf_appna_notifications' ),
];
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Copy synthetic form input; the actual settings callback verifies its generated nonce.
$_REQUEST = $_POST;
ob_start();
$provider->settings_page();
ob_end_clean();
check( Events::allowed(), 'Saving only the sending checkbox enables notifications' );
$provider->website_bridge();
check( wp_script_is( 'craf-appna-notifications', 'enqueued' ), 'Bridge uses native script enqueue API when sending is On' );
$bridge = wp_scripts()->registered['craf-appna-notifications'];
check( strpos( implode( '', (array) ( $bridge->extra['before'] ?? [] ) ), 'crafAppnaNotifications' ) !== false, 'Bridge config is attached as inline script data' );
Events::emit(
    'custom.created', [], [
        'id' => 'ui-withdraw',
    ]
);
$old_epoch = Events::delivery_epoch();

$_POST = [
    'craf_appna_save_notifications' => '1',
    '_wpnonce'                      => wp_create_nonce( 'craf_appna_notifications' ),
];
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Copy synthetic form input; the actual settings callback verifies its generated nonce.
$_REQUEST = $_POST;
ob_start();
$provider->settings_page();
ob_end_clean();
check( ! Events::allowed() && ! $event_queue->is_queued() && Events::delivery_epoch() !== $old_epoch, 'Saving Off cancels pending work and invalidates its delivery epoch' );
$_POST    = [];
$_REQUEST = [];
ob_start();
$provider->settings_page();
$html = ob_get_clean();
check( strpos( $html, 'name="craf_appna_notifications_enabled" value="1"  checked=' ) === false, 'Saved sending opt-out stays off when the settings page is reopened' );
$_POST = [
    'craf_appna_save_notifications'    => '1',
    'craf_appna_notifications_enabled' => '1',
    '_wpnonce'                         => wp_create_nonce( 'craf_appna_notifications' ),
];
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic input is verified by the actual settings callback.
$_REQUEST = $_POST;
ob_start();
$provider->settings_page();
$html = ob_get_clean();
check( strpos( $html, 'name="craf_appna_notifications_enabled" value="1"  checked=' ) !== false && Events::allowed(), 'Saving On resumes sending without a sharing checkbox' );
$_POST    = [];
$_REQUEST = [];
// Explicit uninstall removes only this plugin's persistence and scheduled work.
WP_Session_Tokens::get_instance( $user )->create( time() + 3600 );
$core_sessions = get_user_meta( $user, 'session_tokens', true );
$core_roles    = get_user_meta( $user, $wpdb->prefix . 'capabilities', true );
update_option( 'other_plugin_fixture', 'keep' );
wp_schedule_single_event( time() + 120, 'other_plugin_fixture_cron' );
update_option( 'craf_appna_notification_epoch', 'uninstall' );
update_option( 'craf_appna_notifications_enabled', 'yes' );
Events::emit(
    'custom.created', [], [
        'id' => 'uninstall',
    ]
);
$event_queue->dispatch();
define( 'WP_UNINSTALL_PLUGIN', $appna_test_plugin_dir . '/appnatively.php' );
require $appna_test_plugin_dir . '/uninstall.php';
check( get_option( 'craf_appna_notification_epoch', null ) === null, 'Uninstall removes the delivery epoch' );
check( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'craf_appna_event_bindings' ) ) === null, 'Uninstall removes encrypted bindings table' );
check( ! $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'craf_appna_nq_%'" ), 'Uninstall removes queue batches and retry state' );
check( get_option( 'other_plugin_fixture' ) === 'keep' && wp_next_scheduled( 'other_plugin_fixture_cron' ), 'Uninstall preserves other plugin settings and cron' );
check( get_user_meta( $user, 'session_tokens', true ) === $core_sessions && get_user_meta( $user, $wpdb->prefix . 'capabilities', true ) === $core_roles, 'Uninstall preserves native cookie sessions and roles' );
$result = [
    'query_profiles' => $appna_profiles,
    'checks'         => $checks,
    'wordpress'      => $GLOBALS['wp_version'],
    'php'            => PHP_VERSION,
    'mysql'          => $connection->server_info,
];
file_put_contents( dirname( $socket ) . '/native-result-' . gmdate( 'Ymd-His' ) . '.json', wp_json_encode( $result ) );
echo "PASS: {$checks} native WordPress/MySQL integration checks\n";

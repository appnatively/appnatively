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
$database = 'appna_security_test';
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
$install_only = ( $argv[2] ?? '' ) === '--install';
if ( ! defined( 'APPNA_SECURITY_WORKER' ) && ! $install_only ) {
    $connection->query( 'DROP DATABASE IF EXISTS appna_security_test' );
    $connection->query( 'CREATE DATABASE appna_security_test' );
}
$appna_test_plugin_dir = dirname( __DIR__, 2 );
$core                  = dirname( __DIR__, 5 ) . '/';
$scratch               = dirname( $socket ) . '/security-wordpress-content';
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
$GLOBALS['table_prefix'] = 'appna_security_';
$_SERVER['HTTP_HOST']    = 'appna.example.test';
$_SERVER['SERVER_NAME']  = 'appna.example.test';
$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
$_SERVER['SERVER_PORT']  = '443';
$_SERVER['HTTPS']        = 'on';
$_SERVER['REQUEST_URI']  = '/';
// Never load wp-load.php or wp-config.php. Only installed core source is used.
require_once ABSPATH . 'wp-includes/plugin.php';
$http = [];
add_action(
    'muplugins_loaded', static function (): void { global $wpdb; $wpdb->hide_errors();
    }
);
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
if ( ! defined( 'APPNA_SECURITY_WORKER' ) ) {
    if ( ! $install_only ) {
        // Install core in another PHP process before Fluent Forms boots its hooks.
        $pipes     = [];
        $installer = proc_open(
            [
                PHP_BINARY,
                __FILE__,
                $socket,
                '--install'
            ], [
                0 => [
                    'pipe',
                    'r'
                ],
                1 => [
                    'pipe',
                    'w'
                ],
                2 => [
                    'pipe',
                    'w'
                ]
            ], $pipes
        );
        fclose( $pipes[0] );
        $output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        if ( proc_close( $installer ) !== 0 ) {
            throw new RuntimeException( 'Disposable core installation failed: ' . $output );
        }
    }
    $loader = "<?php\n";
    if ( ! $install_only ) {
        $loader .= 'require ' . var_export( dirname( $appna_test_plugin_dir ) . '/fluentform/fluentform.php', true ) . ";\n";
    }
    $loader .= 'require ' . var_export( $appna_test_plugin_dir . '/appnatively.php', true ) . ";\n";
    file_put_contents( WPMU_PLUGIN_DIR . '/appna.php', $loader );
}
add_filter( 'pre_wp_mail', '__return_true' );
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( $install_only ) {
    wp_install( 'Security isolation test', 'security-test-admin', 'fixture@example.test', false, '', 'test-only-password-4581' );
    exit;
}
if ( ! defined( 'APPNA_SECURITY_WORKER' ) ) {
    \FluentForm\Database\DBMigrator::run();
}

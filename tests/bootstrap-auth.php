<?php
/** Focused REST/auth tests without loading third-party integration fixtures. */

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/vendor-src/yoast/phpunit-polyfills' );
}

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "Could not find $tests_dir/includes/functions.php\n";
    exit( 1 );
}

require_once $tests_dir . '/includes/functions.php';
if ( getenv( 'CI' ) === 'true' ) {
    tests_add_filter(
        'muplugins_loaded', static function () {
            add_filter(
                'pre_http_request', static function ( $response ) {
                    return $response !== false ? $response : new \WP_Error( 'appna_isolated_test', 'Outbound HTTP requires an explicit test fixture.' );
                }, PHP_INT_MAX
            );
        }
    );
}
tests_add_filter(
    'muplugins_loaded', static function() {
        require dirname( __DIR__ ) . '/appnatively.php';
    }
);
require $tests_dir . '/includes/bootstrap.php';

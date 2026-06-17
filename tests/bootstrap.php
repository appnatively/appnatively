<?php
/**
 * PHPUnit bootstrap file
 */

// Path to the PHPUnit Polyfills.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/vendor-src/yoast/phpunit-polyfills' );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "Could not find $_tests_dir/includes/functions.php\n";
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    $wp_core_dir = getenv( 'WP_CORE_DIR' );
    if ( ! $wp_core_dir ) {
        $wp_core_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress';
    }
    $wp_plugins_dir = $wp_core_dir . '/wp-content/plugins';

    // Load FluentForm
    if ( file_exists( $wp_plugins_dir . '/fluentform/fluentform.php' ) ) {
        require_once $wp_plugins_dir . '/fluentform/fluentform.php';
        if ( class_exists( '\FluentForm\Database\DBMigrator' ) ) {
            \FluentForm\Database\DBMigrator::run();
        }
    }

    // Load FormGent
    if ( file_exists( $wp_plugins_dir . '/formgent/formgent.php' ) ) {
        require_once $wp_plugins_dir . '/formgent/formgent.php';
        if ( class_exists( '\FormGent\Database\Setup' ) ) {
            ( new \FormGent\Database\Setup() )->execute();
        }
    }

    // Load Forminator
    $_forminator_path = '';
    if ( file_exists( $wp_plugins_dir . '/forminator/forminator.php' ) ) {
        $_forminator_path = $wp_plugins_dir . '/forminator/forminator.php';
    } elseif ( file_exists( dirname( __DIR__, 2 ) . '/forminator/forminator.php' ) ) {
        $_forminator_path = dirname( __DIR__, 2 ) . '/forminator/forminator.php';
    }

    if ( $_forminator_path ) {
        require_once $_forminator_path;
    }
    unset( $_forminator_path );

    // Load Contact Form 7
    if ( file_exists( $wp_plugins_dir . '/contact-form-7/wp-contact-form-7.php' ) ) {
        require_once $wp_plugins_dir . '/contact-form-7/wp-contact-form-7.php';
    }

    // Load SureForms
    $_sureforms_path = '';
    if ( file_exists( $wp_plugins_dir . '/sureforms/sureforms.php' ) ) {
        $_sureforms_path = $wp_plugins_dir . '/sureforms/sureforms.php';
    } elseif ( file_exists( dirname( __DIR__, 2 ) . '/sureforms/sureforms.php' ) ) {
        $_sureforms_path = dirname( __DIR__, 2 ) . '/sureforms/sureforms.php';
    }

    if ( $_sureforms_path ) {
        require_once $_sureforms_path;
    }
    unset( $_sureforms_path );

    // Load WPForms
    $_wpforms_path = '';
    if ( file_exists( $wp_plugins_dir . '/wpforms-lite/wpforms.php' ) ) {
        $_wpforms_path = $wp_plugins_dir . '/wpforms-lite/wpforms.php';
    } elseif ( file_exists( $wp_plugins_dir . '/wpforms/wpforms.php' ) ) {
        $_wpforms_path = $wp_plugins_dir . '/wpforms/wpforms.php';
    } elseif ( file_exists( dirname( __DIR__, 2 ) . '/wpforms-lite/wpforms.php' ) ) {
        $_wpforms_path = dirname( __DIR__, 2 ) . '/wpforms-lite/wpforms.php';
    }

    if ( $_wpforms_path ) {
        require_once $_wpforms_path;

        // When WPForms is loaded via a symlink, __FILE__ resolves to the real path
        // outside WP_PLUGIN_DIR, causing plugin_basename() to return the wrong value.
        // This prevents the Requirements validator from finding per-plugin config and
        // the license check fails (free Lite has no license key).
        // Force-load the main class if wpforms() wasn't defined.
        if ( ! function_exists( 'wpforms' ) ) {
            require_once WPFORMS_PLUGIN_DIR . '/src/WPForms.php';

            if ( function_exists( 'wpforms' ) ) {
                // Create singleton; constructor registers objects() on plugins_loaded.
                wpforms();
            }
        }
    }
    unset( $_wpforms_path );

    require dirname( __DIR__ ) . '/appnatively.php';

    // Reset and create database tables for tests
    $setup = new \AppNatively\Database\Setup;
    $setup->drop();
    $setup->execute();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

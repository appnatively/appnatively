<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\App;
use AppNatively\Database\Setup;

/**
 * Plugin Name:       App Natively
 * Description:       This plugin is build with WpMVC framework
 * Version:           0.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Tested up to:      6.9
 * Author:            WpMVC
 * Author URI:        http://github.com/wpmvc
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       appnatively
 * Domain Path:       /languages
 */

require_once __DIR__ . '/vendor/vendor-src/autoload.php';
require_once __DIR__ . '/app/Helpers/helper.php';

final class Appnatively
{
    public static Appnatively $instance;

    public static function instance(): Appnatively {
        if ( empty( self::$instance ) ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function load() {
        // Run Activation Tasks
        register_activation_hook(
            __FILE__, function() {
                ( new Setup )->execute();
            } 
        );

        $application = App::instance();

        $application->boot( __FILE__, __DIR__ );

        /**
         * Fires once activated plugins have loaded.
         *
         */
        add_action(
            'plugins_loaded', function () use ( $application ): void {

                do_action( 'appnatively_before_load' );

                $application->load();

                do_action( 'appnatively_after_load' );
            }
        );
    }
}

Appnatively::instance()->load();

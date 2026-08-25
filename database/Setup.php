<?php

namespace Crafium\AppNatively\Database;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Support\Settings;

/**
 * Class Setup
 *
 * Runs on activation. The plugin creates no tables of its own — everything it
 * reads lives in WordPress or in the integrated plugins — so this only has to
 * establish the options the settings screen manages.
 */
class Setup {
    /**
     * Prepare the plugin's options on activation.
     *
     * @return void
     */
    public function execute() {
        // Mint the Studio connection key up front, so the settings screen has
        // one to show the moment an administrator opens it.
        Settings::get_site_key();

        if ( false === get_option( Settings::OPTION_ENABLED, false ) ) {
            Settings::set_api_enabled( true );
        }
    }

    /**
     * Remove the plugin's options.
     *
     * @return void
     */
    public function drop() {
        Settings::delete_all();
    }
}

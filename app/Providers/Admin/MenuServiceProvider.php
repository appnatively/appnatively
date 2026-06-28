<?php

namespace Crafium\AppNatively\App\Providers\Admin;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\View\View;

class MenuServiceProvider extends Provider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        add_action( 'admin_menu', [$this, 'action_admin_menu'] );
    }

    /**
     * Action to register the admin menu and submenus.
     *
     * @return void
     */
    public function action_admin_menu() {
        add_menu_page( "App Natively", 'App Natively', 'manage_options', 'craf_appna', [$this, 'dashboard'], 'dashicons-admin-generic', 30 );
    }

    /**
     * Render the overview page content.
     *
     * @return void
     */
    public function dashboard() {
        View::render( 'index' );
    }
}
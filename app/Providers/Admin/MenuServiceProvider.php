<?php

namespace AppNatively\App\Providers\Admin;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Contracts\Provider;
use AppNatively\WpMVC\View\View;

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
        add_menu_page( "Appnatively", 'Appnatively', 'manage_options', 'appnatively-menu', function () { }, 'dashicons-admin-generic', 30 );
        add_submenu_page( 'appnatively-menu', esc_html__( 'Overview', 'appnatively' ), esc_html__( 'Overview', 'appnatively' ), 'manage_options', 'appnatively', [$this, 'overview'] );

        remove_submenu_page( 'appnatively-menu', 'appnatively-menu' );
    }

    /**
     * Render the overview page content.
     *
     * @return void
     */
    public function overview() {
        View::render( 'index' );
    }
}
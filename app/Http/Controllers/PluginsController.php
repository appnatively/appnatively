<?php

namespace AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class PluginsController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $integrated_plugins_list = apply_filters(
            'appnatively_integrated_plugins', [
                "woocommerce" => [ "category" => "ecommerce", "label" => "WooCommerce" ],
                "fluent-cart" => [ "category" => "ecommerce", "label" => "Fluent Cart" ],
                "formgent"    => [ "category" => "form",      "label" => "FormGent" ],
            ]
        );

        $activated_plugins = [];

        foreach ( $integrated_plugins_list as $slug => $plugin ) {
            if ( is_plugin_active( $slug . '/' . $slug . '.php' ) ) {
                $activated_plugins[ $plugin['category'] ][ $slug ] = $plugin['label'];
            }
        }

        return Response::send(
            [
                "plugins" => $activated_plugins
            ]
        );
    }
}
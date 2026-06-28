<?php

namespace Crafium\AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class PluginsController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $integrated_plugins_list = apply_filters(
            "craf_appna_integrated_plugins", [
                "woocommerce"    => [ "category" => "ecommerce", "label" => "WooCommerce" ],
                "fluent-cart"    => [ "category" => "ecommerce", "label" => "Fluent Cart" ],
                "formgent"       => [ "category" => "form",      "label" => "FormGent" ],
                "fluentform"     => [ "category" => "form",      "label" => "Fluent Form" ],
                "directorist"    => [ "category" => "directory", "label" => "Directorist", "file" => "directorist/directorist-base.php"], // add path if plugin folder and root file is not same
                "contact-form-7" => [ "category" => "form", "label" => "Contact Form 7", "file" => "contact-form-7/wp-contact-form-7.php"],
                "everest-forms"  => [ "category" => "form", "label" => "Everest Forms"],
                "formidable"     => [ "category" => "form", "label" => "Formidable"],
                "forminator"     => [ "category" => "form", "label" => "Forminator"],
                "gutena-forms"   => [ "category" => "form", "label" => "Gutena Forms"],
                "happyforms"     => [ "category" => "form", "label" => "Happy Forms"],
                "sureforms"      => [ "category" => "form", "label" => "SureForms"],
                "weforms"        => [ "category" => "form", "label" => "WeForms"],
                "wpforms-lite"   => [ "category" => "form", "label" => "WPForms", "file" => "wpforms-lite/wpforms.php"],
            ]
        );

        $activated_plugins = [];

        foreach ( $integrated_plugins_list as $slug => $plugin ) {
            $plugin_file = isset( $plugin['file'] ) ? $plugin['file'] : $slug . '/' . $slug . '.php';
            if ( is_plugin_active( $plugin_file ) ) {
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
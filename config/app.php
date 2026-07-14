<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Integrations\Forms\GutenaForms;
use Crafium\AppNatively\App\Providers\AuthServiceProvider;
use Crafium\AppNatively\App\Http\Middleware\EnsureIsUserAdmin;
use Crafium\AppNatively\App\Integrations\FluentCart;
use Crafium\AppNatively\App\Integrations\Directorist;
use Crafium\AppNatively\App\Integrations\Forms\FormGent;
use Crafium\AppNatively\App\Integrations\Forms\FluentForm;
use Crafium\AppNatively\App\Integrations\Forms\EverestForms;
use Crafium\AppNatively\App\Integrations\Forms\ContactForm7;
use Crafium\AppNatively\App\Integrations\Forms\Formidable;
use Crafium\AppNatively\App\Integrations\Forms\Forminator;
use Crafium\AppNatively\App\Integrations\Forms\HappyForms;
use Crafium\AppNatively\App\Integrations\Forms\SureForms;
use Crafium\AppNatively\App\Integrations\Forms\WeForms;
use Crafium\AppNatively\App\Integrations\Forms\WPForms;
use Crafium\AppNatively\App\Models\Comment;
use Crafium\AppNatively\App\Models\Post;
use Crafium\AppNatively\App\Models\Term;
use Crafium\AppNatively\App\Models\TermTaxonomy;
use Crafium\AppNatively\App\Models\User;
use Crafium\AppNatively\App\Integrations\Woocommerce;
use Crafium\AppNatively\App\Integrations\SureCart;
use Crafium\AppNatively\WpMVC\Helpers\Helpers;

return [
    /**
     * The version of the plugin.
     */
    'version'                     => Helpers::get_plugin_version( 'appnatively' ),

    /**
     * Configuration for the REST API.
     */
    'rest_api'                    => [
        /**
         * The namespace for the REST API.
         */
        'namespace' => 'craf_appna',
        
        /**
         * The versions of the REST API.
         */
        'versions'  => ['v1']
    ],

    /**
     * Configuration for the AJAX API.
     */
    'ajax_api'                    => [
        /**
         * The namespace for the AJAX API.
         */
        'namespace' => 'craf_appna',
        
        /**
         * The versions of the AJAX API.
         */
        'versions'  => []
    ],

    /**
     * Service providers for the plugin.
     */
    'providers'                   => [
        //Core
        AuthServiceProvider::class,

        // Ecommerce Integrations
        Woocommerce::class,
        FluentCart::class,
        SureCart::class,

        // Directory Integrations
        Directorist::class,

        // Forms Integrations
        FormGent::class,
        FluentForm::class,
        EverestForms::class,
        ContactForm7::class,
        Formidable::class,
        Forminator::class,
        GutenaForms::class,
        HappyForms::class,
        SureForms::class,
        WeForms::class,
        WPForms::class,
    ],

    /**
     * Service providers for the admin area of the plugin.
     */
    'admin_providers'             => [
        // MenuServiceProvider::class,
    ],

    /**
     * Middleware configuration for the plugin.
     */
    'middleware'                  => [
        /**
         * Middleware for admin routes.
         */
        'admin' => EnsureIsUserAdmin::class
    ],

    /**
     * The database option key for storing migration information.
     */
    'migration_db_option_key'     => 'craf_appna_migrations',

    /**
     * List of migrations for the plugin.
     */
    'migrations'                  => [
        // 'test-migration' => TestMigration::class,
    ],

    /**
     * The WpMVC provided hooks prefix.
     */
    'hook_prefix'                 => 'craf_appna',

    /**
     * This configuration option defines a hook that will fire before executing the route callback,
     * such as before a controller action. It provides two parameters:
     * 
     * @param WP_REST_Request $wp_rest_request The current REST request object.
     * @param string $full_route The full route being accessed.
     */
    'rest_response_action_hook'   => 'craf_appna_rest_response_action',

    /**
     * Configuration for the REST API response filter hook.
     *
     * This filter hook allows overriding the entire REST API response.
     * 
     * @param $response The response object from the controller.
     * @param WP_REST_Request  $wp_rest_request The request object.
     * @param string           $full_route The full route of the request.
     */
    'rest_response_filter_hook'   => 'craf_appna_rest_response_filter',

    /**
     * This filter hook that can override all REST API permissions.
     * 
     * @param mixed $permission The current permission setting.
     * @param mixed $middleware The middleware being applied.
     * @param string $full_route The full route of the API endpoint.
     */
    'rest_permission_filter_hook' => 'craf_appna_rest_permission_filter',

    /**
     * The registered morph map for polymorphic relationships.
     */
    'morph_map'                   => [
        'user'     => User::class,
        'post'     => Post::class,
        'comment'  => Comment::class,
        'term'     => Term::class,
        'taxonomy' => TermTaxonomy::class,
    ],
];

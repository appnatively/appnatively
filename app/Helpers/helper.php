<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\WpMVC\App;
use Crafium\AppNatively\WpMVC\Container\Container;
use Crafium\AppNatively\WpMVC\Helpers\Date;

/**
 * Get the application instance.
 *
 * @return App
 */
function craf_appna(): App {
    return App::$instance;
}

/**
 * Get the configuration value.
 *
 * @param string $config_key The configuration key.
 * @return mixed
 */
function craf_appna_config( string $config_key ) {
    return craf_appna()::get_config()->get( $config_key );
}

/**
 * Get the application configuration value.
 *
 * @param string $config_key The configuration key.
 * @return mixed
 */
function craf_appna_app_config( string $config_key ) {
    return craf_appna_config( "app.{$config_key}" );
}

/**
 * Get the plugin version.
 *
 * @return string
 */
function craf_appna_version(): string {
    return craf_appna_app_config( 'version' );
}

/**
 * Get the container instance.
 *
 * @return Container
 */
function craf_appna_container(): Container {
    return craf_appna()::get_container();
}

/**
 * Resolve a service instance from the container.
 *
 * @template T
 * @param class-string<T> $class Service ID or class name to resolve.
 * @param array $params Parameters for resolution.
 * @return T
 */
function craf_appna_resolve( string $class, array $params = [] ) {
    return craf_appna_container()->get( $class, $params );
}

/**
 * Create a new instance of the given class (Factory).
 *
 * @template T
 * @param class-string<T> $class Class name to resolve.
 * @param array $params Parameters for the constructor.
 * @return T A new instance.
 */
function craf_appna_make( string $class, array $params = [] ) {
    return craf_appna_container()->make( $class, $params );
}

/**
 * Get the plugin URL.
 *
 * @param string $url Optional. Extra path to append to the URL.
 * @return string
 */
function craf_appna_url( string $url = '' ): string {
    return craf_appna()->get_url( $url );
}

/**
 * Get the plugin directory path.
 *
 * @param string $dir Optional. Extra path to append to the directory.
 * @return string
 */
function craf_appna_dir( string $dir = '' ): string {
    return craf_appna()->get_dir( $dir );
}

/**
 * Get a new Date instance for 'now'.
 *
 * @param DateTimeZone|null $timezone Optional. The timezone. Defaults to wp_timezone().
 * @return Date
 */
function craf_appna_now( ?DateTimeZone $timezone = null ): Date {
    return Date::now( $timezone );
}

/**
 * Get the verified fields from the requested fields string.
 *
 * @param string|null $fields The requested fields.
 * @param array $allowed_fields The allowed fields.
 * @return array
 */
function craf_appna_get_verified_fields( ?string $fields, array $allowed_fields ): array {
    if ( empty( $fields ) ) {
        return $allowed_fields;
    }

    $fields = array_map( "trim", explode( ",", $fields ) );

    return array_values( array_intersect( $fields, $allowed_fields ) );
}

/**
 * Get the list of supported directory `integration` slugs.
 *
 * Single source of truth for the Directory REST controllers' `integration`
 * validation rule, mirroring the slugs each Integrations/*.php provider
 * registers its `craf_appna_directory_{$integration}_*` filters under.
 *
 * @return string[]
 */
function craf_appna_get_directory_integrations(): array {
    /**
     * Filters the recognised directory integration slugs.
     *
     * Extend this when registering a directory provider of your own, or the
     * `integration` parameter naming it will be rejected before dispatch.
     *
     * @param string[] $integrations Slugs each provider registers its
     *                               craf_appna_directory_{slug}_* filters under.
     */
    return apply_filters(
        'craf_appna_directory_integrations', [
            "directorist",
            "geodirectory",
            "business-directory-plugin",
            "classified-listing",
            "hivepress",
            "adirectory",
        ]
    );
}

/**
 * Get the list of supported e-commerce `integration` slugs.
 *
 * Mirrors craf_appna_get_directory_integrations() so every controller that
 * interpolates `integration` into a hook name can constrain it to a known
 * set first, rather than building hook names from arbitrary input.
 *
 * @return string[]
 */
function craf_appna_get_ecommerce_integrations(): array {
    /**
     * Filters the recognised e-commerce integration slugs.
     *
     * @param string[] $integrations Slugs each provider registers its
     *                               craf_appna_ecommerce_{slug}_* filters under.
     */
    return apply_filters(
        'craf_appna_ecommerce_integrations', [
            "woocommerce",
            "fluent-cart",
            "surecart",
        ]
    );
}

/**
 * Get the list of supported form `integration` slugs.
 *
 * These are the values each Form provider returns from get_key(), which are
 * not always the plugin's directory slug — WPForms registers under "wpforms",
 * not "wpforms-lite".
 *
 * @return string[]
 */
function craf_appna_get_form_integrations(): array {
    /**
     * Filters the recognised form integration slugs.
     *
     * @param string[] $integrations Slugs each provider returns from get_key().
     */
    return apply_filters(
        'craf_appna_form_integrations', [
            "formgent",
            "fluentform",
            "contact-form-7",
            "wpforms",
            "ninjaforms",
            "formidable",
            "forminator",
            "everest-forms",
            "sureforms",
            "weforms",
            "happyforms",
            "gutena-forms",
            "bitform",
        ]
    );
}

/**
 * Build a request-validator `in:` rule from a list of allowed slugs.
 *
 * @param string[] $allowed The allowed values.
 * @return string
 */
function craf_appna_in_rule( array $allowed ): string {
    return "in:" . implode( ",", $allowed );
}

/**
 * Determine whether a plugin is active, without loading admin includes.
 *
 * is_plugin_active() lives in wp-admin/includes/plugin.php, which is not part
 * of a front-end or REST request. This reads the same two options that
 * function does so the check works anywhere.
 *
 * @param string $plugin_file Plugin file path relative to the plugins dir.
 * @return bool
 */
function craf_appna_is_plugin_active( string $plugin_file ): bool {
    if ( in_array( $plugin_file, (array) get_option( 'active_plugins', [] ), true ) ) {
        return true;
    }

    if ( ! is_multisite() ) {
        return false;
    }

    $network_plugins = get_site_option( 'active_sitewide_plugins', [] );

    return isset( $network_plugins[ $plugin_file ] );
}

/**
 * Determine whether a post is password protected.
 *
 * A password-protected post keeps the `publish` status, so a `post_status`
 * check alone lets one through. On the web the password is enforced when the
 * content is rendered — `get_the_excerpt()` returns a placeholder, and the
 * loop substitutes the password form — but the `the_content` filter chain
 * carries no such guard, and neither does reading `post_content` directly.
 * This API has no password form to fall back to, so protected entries are
 * excluded from it outright rather than served with their body exposed.
 *
 * @param int|WP_Post|null $post The post or post id.
 * @return bool
 */
function craf_appna_is_post_password_protected( $post ): bool {
    $post = get_post( $post );

    return $post instanceof WP_Post && '' !== (string) $post->post_password;
}

/**
 * Read a route parameter, preferring the value bound from the URL path.
 *
 * WP_REST_Request::get_parameter_order() ranks GET above URL, so `?id=99` on
 * `/orders/5` wins and a route's `->where( 'id', '\d+' )` constraint stops
 * being the last word on what the controller sees. Path parameters are matched
 * against that constraint by the REST server, so they are the ones to trust.
 *
 * @param WP_REST_Request $request The current request.
 * @param string          $name    The parameter name.
 * @return mixed
 */
function craf_appna_route_param( $request, string $name ) {
    if ( ! $request instanceof WP_REST_Request ) {
        return null;
    }

    $url_params = $request->get_url_params();

    return array_key_exists( $name, $url_params ) ? $url_params[ $name ] : $request->get_param( $name );
}

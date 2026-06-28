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

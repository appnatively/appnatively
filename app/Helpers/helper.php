<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\App;
use AppNatively\WpMVC\Container\Container;
use AppNatively\WpMVC\Helpers\Date;

/**
 * Get the application instance.
 *
 * @return App
 */
function appnatively(): App {
    return App::$instance;
}

/**
 * Get the configuration value.
 *
 * @param string $config_key The configuration key.
 * @return mixed
 */
function appnatively_config( string $config_key ) {
    return appnatively()::get_config()->get( $config_key );
}

/**
 * Get the application configuration value.
 *
 * @param string $config_key The configuration key.
 * @return mixed
 */
function appnatively_app_config( string $config_key ) {
    return appnatively_config( "app.{$config_key}" );
}

/**
 * Get the plugin version.
 *
 * @return string
 */
function appnatively_version(): string {
    return appnatively_app_config( 'version' );
}

/**
 * Get the container instance.
 *
 * @return Container
 */
function appnatively_container(): Container {
    return appnatively()::get_container();
}

/**
 * Resolve a service instance from the container.
 *
 * @template T
 * @param class-string<T> $class Service ID or class name to resolve.
 * @param array $params Parameters for resolution.
 * @return T
 */
function appnatively_resolve( string $class, array $params = [] ) {
    return appnatively_container()->get( $class, $params );
}

/**
 * Create a new instance of the given class (Factory).
 *
 * @template T
 * @param class-string<T> $class Class name to resolve.
 * @param array $params Parameters for the constructor.
 * @return T A new instance.
 */
function appnatively_make( string $class, array $params = [] ) {
    return appnatively_container()->make( $class, $params );
}

/**
 * Get the plugin URL.
 *
 * @param string $url Optional. Extra path to append to the URL.
 * @return string
 */
function appnatively_url( string $url = '' ): string {
    return appnatively()->get_url( $url );
}

/**
 * Get the plugin directory path.
 *
 * @param string $dir Optional. Extra path to append to the directory.
 * @return string
 */
function appnatively_dir( string $dir = '' ): string {
    return appnatively()->get_dir( $dir );
}

/**
 * Get a new Date instance for 'now'.
 *
 * @param DateTimeZone|null $timezone Optional. The timezone. Defaults to wp_timezone().
 * @return Date
 */
function appnatively_now( ?DateTimeZone $timezone = null ): Date {
    return Date::now( $timezone );
}

/**
 * Get the verified fields from the requested fields string.
 *
 * @param string|null $fields The requested fields.
 * @param array $allowed_fields The allowed fields.
 * @return array
 */
function appnatively_get_verified_fields( ?string $fields, array $allowed_fields ): array {
    if ( empty( $fields ) ) {
        return $allowed_fields;
    }

    $fields = array_map( "trim", explode( ",", $fields ) );

    return array_values( array_intersect( $fields, $allowed_fields ) );
}

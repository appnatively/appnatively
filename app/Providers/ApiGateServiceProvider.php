<?php

namespace Crafium\AppNatively\App\Providers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use WP_Error;

/**
 * Class ApiGateServiceProvider
 *
 * Gives the site owner a single switch over everything this plugin exposes.
 * Without it, the only way to stop the bridge answering requests would be to
 * deactivate the plugin and lose its configuration.
 */
class ApiGateServiceProvider extends Provider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {}

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        add_filter( 'craf_appna_rest_permission_filter', [$this, 'gate_requests'], 5, 3 );
        add_filter( 'rest_allowed_cors_headers', [$this, 'allow_connection_key_header'] );
    }

    /**
     * Let the connection key header survive a cross-origin preflight.
     *
     * AppNatively Studio runs on a different origin to the site it is reading,
     * and X-AppNatively-Key is a custom header — which makes the browser send a
     * preflight OPTIONS first. WordPress answers that with a fixed
     * Access-Control-Allow-Headers list (Authorization, X-WP-Nonce,
     * Content-Disposition, Content-MD5, Content-Type), so without this the
     * browser blocks the real request before it is ever sent.
     *
     * @param string[] $allow_headers Headers WordPress will accept cross-origin.
     * @return string[]
     */
    public function allow_connection_key_header( $allow_headers ) {
        $allow_headers   = is_array( $allow_headers ) ? $allow_headers : [];
        $allow_headers[] = 'X-AppNatively-Key';

        // The cart endpoints read these two from the app, which is not a browser
        // and so never preflights — but the Studio preview renders in one.
        $allow_headers[] = 'X-Cart-Id';
        $allow_headers[] = 'X-WC-Session';

        return array_values( array_unique( $allow_headers ) );
    }

    /**
     * Refuse every route while the bridge is switched off.
     *
     * This catches the routes that carry no middleware at all. Every middleware
     * also refuses on its own, so a route that requires a token or a key does
     * not depend on this filter still being wired up to enforce that.
     *
     * @param mixed  $permission The permission resolved so far.
     * @param mixed  $middleware The middleware applied to the route.
     * @param string $full_route The route being requested.
     * @return mixed
     */
    public function gate_requests( $permission, $middleware, $full_route ) {
        if ( Settings::is_api_enabled() ) {
            return $permission;
        }

        return Settings::api_disabled_error();
    }
}

<?php
/**
 * Require a valid app Bearer token.
 *
 * @package Crafium\AppNatively\App\Http\Middleware
 */

namespace Crafium\AppNatively\App\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\WpMVC\Routing\Contracts\Middleware;
use WP_REST_Request;
use WP_Error;

/**
 * Class EnsureIsAuthenticated
 *
 * Resolves the request's Bearer token and establishes the current user before
 * the controller runs, so authorisation is settled in permission_callback
 * where WordPress expects it rather than inside each controller body.
 */
class EnsureIsAuthenticated implements Middleware {
    /**
     * Handle an incoming request.
     *
     * @param WP_REST_Request $wp_rest_request The current request instance.
     * @param mixed           $next            The next middleware closure in the stack.
     * @return bool|WP_Error
     */
    public function handle( WP_REST_Request $wp_rest_request, $next ) {
        // Refuse here rather than letting the request through to the gate
        // filter. Passing it on would mean this middleware's whole purpose —
        // requiring a token — depends on a separate filter still being wired
        // up, which is the wrong way round for an authentication check.
        if ( ! Settings::is_api_enabled() ) {
            return Settings::api_disabled_error();
        }

        if ( ! Auth::authenticate( $wp_rest_request ) ) {
            return new WP_Error(
                'craf_appna_unauthorized',
                __( 'A valid access token is required for this request.', 'appnatively' ),
                [ 'status' => 401 ]
            );
        }

        return $next( $wp_rest_request );
    }
}

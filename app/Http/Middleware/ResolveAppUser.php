<?php
/**
 * Establish the app user when a token is present, without requiring one.
 *
 * @package Crafium\AppNatively\App\Http\Middleware
 */

namespace Crafium\AppNatively\App\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\WpMVC\Routing\Contracts\Middleware;
use WP_REST_Request;

/**
 * Class ResolveAppUser
 *
 * For endpoints that serve signed-in and anonymous callers alike — carts,
 * form submissions — where a guest must still be able to proceed but a signed
 * in user should be recognised as themselves.
 *
 * Without this, a request carrying a perfectly valid token is indistinguishable
 * from an anonymous one: the cart never binds to the account and form entries
 * are recorded with no author.
 */
class ResolveAppUser implements Middleware {
    /**
     * Handle an incoming request.
     *
     * @param WP_REST_Request $wp_rest_request The current request instance.
     * @param mixed           $next            The next middleware closure in the stack.
     * @return bool|\WP_Error
     */
    public function handle( WP_REST_Request $wp_rest_request, $next ) {
        // Refuse here rather than deferring to the gate filter. This middleware
        // lets guests through by design, so passing the request on while the
        // API is off would hand it straight to the controller.
        if ( ! Settings::is_api_enabled() ) {
            return Settings::api_disabled_error();
        }

        // An absent or expired token is not an error here — it just means the
        // caller proceeds as a guest.
        Auth::authenticate( $wp_rest_request );

        return $next( $wp_rest_request );
    }
}

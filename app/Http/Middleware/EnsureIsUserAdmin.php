<?php

namespace Crafium\AppNatively\App\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\WpMVC\Routing\Contracts\Middleware;
use WP_REST_Request;
use WP_Error;

class EnsureIsUserAdmin implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  WP_REST_Request  $wp_rest_request The current request instance.
     * @param  mixed           $next            The next middleware closure in the stack.
     * @return bool|WP_Error Returns true to continue, false to forbid, or WP_Error.
     */
    public function handle( WP_REST_Request $wp_rest_request, $next ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'craf_appna_forbidden',
                __( 'You are not authorized to perform this action.', 'appnatively' ),
                [ 'status' => 403 ]
            );
        }

        return $next( $wp_rest_request );
    }
}
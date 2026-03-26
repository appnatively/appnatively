<?php

namespace AppNatively\App\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Exceptions\Exception;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;

class ConnectionController extends Controller
{
    /**
     * Verifies the connection token.
     * GET /wp-json/appnatively/v1/connection/verify-token
     */
    public function verify_token( Request $request ) {
        $auth_header = $request->get_header( 'Authorization' );
        if ( ! $auth_header || strpos( $auth_header, 'Bearer ' ) !== 0 ) {
            return Response::send( [ 'message' => 'Unauthorized' ], 401 );
        }

        $token        = substr( $auth_header, 7 );
        $stored_token = get_option( 'appnatively_site_token' );

        if ( empty( $stored_token ) || ! hash_equals( $stored_token, $token ) ) {
           return Response::send( [ 'message' => 'Invalid or expired token' ], 401 );
        }

        // Return site info
        return Response::send(
            [
                'siteUrl'   => get_site_url(),
                'blogName'  => get_bloginfo( 'name' ),
                'platform'  => 'wordpress',
                'wpVersion' => get_bloginfo( 'version' ),
            ] 
        );
    }

    /**
     * Retrieves the current connection status.
     * GET /wp-json/appnatively/v1/connection/status
     */
    public function get_status() {
        $status     = get_option( 'appnatively_connection_status', 'disconnected' );
        $linked_app = get_option( 'appnatively_connected_app_name', '' );

        return Response::send(
            [
                'connected' => $status === 'connected',
                'appName'   => $linked_app,
                'siteUrl'   => get_site_url(),
            ] 
        );
    }

    /**
     * Disconnects the site from the platform.
     * DELETE /wp-json/appnatively/v1/connection/disconnect
     */
    public function disconnect() {
        delete_option( 'appnatively_site_token' );
        delete_option( 'appnatively_connection_status' );
        delete_option( 'appnatively_connected_app_name' );
        delete_option( 'appnatively_connected_app_id' );

        return Response::send( [ 'success' => true ] );
    }

    /**
     * Initiates the connection handshake.
     * AJAX action: appnatively_init_connect
     */
    public function init_connect() {
        // Generate a cryptographically random token (32-byte hex)
        $site_token  = wp_generate_password( 64, false );
        $state_nonce = wp_generate_password( 32, false );

        // Store in options with 15-min expiry
        update_option( 'appnatively_site_token', $site_token );
        update_option( 'appnatively_site_token_expiry', time() + ( 15 * MINUTE_IN_SECONDS ) );
        update_option( 'appnatively_state_nonce', $state_nonce );

        // The platform URL should be configurable, for now hardcoding or using a constant
        $platform_url = defined( 'APPNATIVELY_PLATFORM_URL' ) ? APPNATIVELY_PLATFORM_URL : 'https://local.appnatively.com';
        
        $redirect_url = add_query_arg(
            [
                'state'    => $state_nonce,
                'siteUrl'  => get_site_url(),
                'token'    => $site_token,
                'platform' => 'wordpress'
            ], "$platform_url/studio/workspace/dashboard" 
        );

        return Response::send(
            [
                'success'     => true,
                'redirectUrl' => $redirect_url,
            ]
        );
    }
}

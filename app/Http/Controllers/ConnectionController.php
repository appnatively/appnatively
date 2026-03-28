<?php

namespace AppNatively\App\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;
use AppNatively\App\Models\Connection;

class ConnectionController extends Controller
{
    /**
     * Verifies the connection token.
     * GET /wp-json/appnatively/v1/connection/verify-token
     */
    public function verify_token( Request $request ) {
        $auth = $this->authenticate_request( $request );
        if ( is_wp_error( $auth ) ) {
            return Response::send( [ 'message' => $auth->get_error_message() ], 401 );
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
        $connections = Connection::all();
        
        $formatted_connections = [];
        foreach ( $connections as $conn ) {
            $formatted_connections[] = [
                'appId'     => $conn->app_id,
                'appName'   => $conn->app_name,
                'status'    => $conn->status,
                'connected' => $conn->status === 'connected',
            ];
        }

        return Response::send(
            [
                'connections' => $formatted_connections,
                'siteUrl'     => get_site_url(),
            ] 
        );
    }

    /**
     * Disconnects the site from the platform.
     * DELETE /wp-json/appnatively/v1/connection/disconnect
     */
    public function disconnect( Request $request ) {
        $app_id = $request->get_param( 'appId' );

        if ( $app_id ) {
            $connection = Connection::where( 'app_id', $app_id )->first();
            if ( $connection ) {
                $connection->delete();
            }
        } else {
            // Reset everything: Clear the table and any pending handshake tokens
            Connection::truncate();
            delete_option( 'appnatively_site_token' );
            delete_option( 'appnatively_site_token_expiry' );
            delete_option( 'appnatively_state_nonce' );
        }

        return Response::send( [ 'success' => true ] );
    }

    /**
     * Initiates the connection handshake.
     * POST /wp-json/appnatively/v1/connection/init
     */
    public function init_connect() {
        // Generate a 64-character site token and 32-character state nonce
        $site_token  = wp_generate_password( 64, false );
        $state_nonce = wp_generate_password( 32, false );

        // Standardize persistence with 15-min TTL
        update_option( 'appnatively_site_token', $site_token );
        update_option( 'appnatively_site_token_expiry', time() + ( 15 * MINUTE_IN_SECONDS ) );
        update_option( 'appnatively_state_nonce', $state_nonce );

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

    /**
     * Completes the connection from the platform.
     * POST /wp-json/appnatively/v1/connection/connect
     */
    public function post_connect( Request $request ) {
        $auth = $this->authenticate_request( $request );
        if ( is_wp_error( $auth ) ) {
            return Response::send( [ 'message' => $auth->get_error_message() ], 401 );
        }

        $app_name   = $request->get_param( 'appName' );
        $app_id     = $request->get_param( 'appId' );
        $site_token = get_option( 'appnatively_site_token' );

        $connection = Connection::where( 'app_id', $app_id )->first();

        if ( ! $connection ) {
            $connection = new Connection();
            $connection->app_id = $app_id;
        }

        $connection->app_name = $app_name;
        $connection->status   = 'connected';
        $connection->token    = $site_token;
        $connection->site_url = get_site_url();
        $connection->save();

        return Response::send( [ 'success' => true ] );
    }

    /**
     * Private helper to authenticate requests via Bearer token.
     */
    private function authenticate_request( Request $request ) {
        $auth_header = $request->get_header( 'Authorization' );
        if ( ! $auth_header || strpos( $auth_header, 'Bearer ' ) !== 0 ) {
            return new \WP_Error( 'unauthorized', 'Unauthorized' );
        }

        $token  = substr( $auth_header, 7 );
        $expiry = get_option( 'appnatively_site_token_expiry' );
        
        if ( $expiry && time() > (int) $expiry ) {
            return new \WP_Error( 'token_expired', 'Token expired' );
        }

        // Check for connection token
        $connection = Connection::where( 'token', $token )->first();
        if ( $connection ) {
            return true;
        }

        // Fallback for global site_token during init/connect handshake
        $stored_token = get_option( 'appnatively_site_token' );
        if ( $stored_token && hash_equals( $stored_token, $token ) ) {
            return true;
        }

        return new \WP_Error( 'invalid_token', 'Invalid or expired token' );
    }
}

<?php

namespace AppNatively\App\Http\Controllers\Auth;

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\Controller;
use AppNatively\WpMVC\Exceptions\Exception;
use AppNatively\WpMVC\Routing\Response;
use AppNatively\WpMVC\RequestValidator\Request;
use WP_User;

class AuthController extends Controller {
    /**
     * Authenticate user and return a token.
     *
     * @param Request $request
     * @return array
     */
    public function login( Request $request ): array {
        $request->validate(
            [
                'email'    => 'required|email|max:255',
                'password' => 'required|string|max:255',
            ]
        );

        $user = wp_authenticate( sanitize_email( $request->get_param( 'email' ) ), $request->get_param( 'password' ) );

        if ( is_wp_error( $user ) ) {
            throw new Exception( "Invalid email or password", 401 );
        }

        $token = $this->generate_token( $user->ID );

        return Response::send(
            [
                'token' => $token,
                'user'  => $this->transform_user( $user ),
            ] 
        );
    }

    /**
     * Register a new user.
     *
     * @param Request $request
     * @return array
     */
    public function register( Request $request ): array {
        if ( ! get_option( 'users_can_register' ) ) {
            throw new Exception( esc_html__( 'User registration is not allowed on this site.', 'appnatively' ), 403 );
        }

        $request->validate(
            [
                'email'      => 'required|email|max:255',
                'password'   => 'required|string|min:6|max:255',
                'first_name' => 'required|string|min:3|max:255',
                'last_name'  => 'required|string|max:255',
            ] 
        );

        $email = sanitize_email( $request->get_param( 'email' ) );

        if ( email_exists( $email ) ) {
            throw new Exception( esc_html__( 'Email already exists.', 'appnatively' ), 400 );
        }

        $username = $email; // Use email as username
        $user_id  = wp_create_user( $username, $request->get_param( 'password' ), $email );

        if ( is_wp_error( $user_id ) ) {
            throw new Exception( esc_html( $user_id->get_error_message() ), 400 );
        }

        $first_name = sanitize_text_field( $request->get_param( 'first_name' ) );
        $last_name  = sanitize_text_field( $request->get_param( 'last_name' ) );

        wp_update_user(
            [
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ),
            ] 
        );

        $user  = get_userdata( $user_id );
        $token = $this->generate_token( $user_id );

        return Response::send(
            [
                'token' => $token,
                'user'  => $this->transform_user( $user ),
            ] 
        );
    }

    /**
     * Get current authenticated user.
     *
     * @param Request $request
     * @return array
     */
    public function me( Request $request ): array {
        $user = $this->get_authenticated_user( $request );

        if ( ! $user ) {
            throw new Exception( esc_html__( 'Unauthorized', 'appnatively' ), 401 );
        }

        return Response::send( $this->transform_user( $user ) );
    }

    /**
     * Generate a short-lived, one-time-use autologin token for WebView checkout.
     *
     * This token is separate from the API auth token and is stored as a
     * transient (expires in 5 minutes). It is deleted immediately after use
     * in handle_autologin(), so even if it leaks from URL logs it cannot
     * be replayed.
     *
     * @param Request $request
     * @return array
     */
    public function autologin_token( Request $request ): array {
        $user = $this->get_authenticated_user( $request );

        if ( ! $user ) {
            throw new Exception( esc_html__( 'Unauthorized', 'appnatively' ), 401 );
        }

        $token        = bin2hex( random_bytes( 32 ) );
        $hashed_token = hash( 'sha256', $token );

        // Store as a transient — auto-expires in 5 minutes, one-time use
        set_transient( 'appnatively_autologin_' . $hashed_token, $user->ID, 5 * MINUTE_IN_SECONDS );

        return Response::send( [ 'autologin_token' => $token ] );
    }

    /**
     * Logout user (clear token).
     *
     * @param Request $request
     * @return array
     */
    public function logout( Request $request ): array {
        $token = $this->get_token_from_header( $request );

        if ( $token ) {
            $hashed_token = hash( 'sha256', $token );
            delete_metadata( 'user', 0, 'appnatively_auth_token', $hashed_token, true );
        }

        return Response::send( [ 'success' => true ] );
    }

    /**
     * Send password reset email.
     *
     * @param Request $request
     * @return array
     */
    public function forgot_password( Request $request ): array {
        $request->validate(
            [
                'email' => 'required|email|max:255',
            ] 
        );

        $user = get_user_by( 'email', sanitize_email( $request->get_param( 'email' ) ) );

        if ( ! $user ) {
            // Don't reveal if user exists for security, just return success
            return Response::send( [ 'success' => true ] );
        }

        $errors = retrieve_password( $user->user_login );

        if ( is_wp_error( $errors ) ) {
            throw new Exception( esc_html( $errors->get_error_message() ), 400 );
        }

        return Response::send( [ 'success' => true ] );
    }

    /**
     * Update user profile.
     *
     * @param Request $request
     * @return array
     */
    public function update_profile( Request $request ): array {
        $user = $this->get_authenticated_user( $request );

        if ( ! $user ) {
            throw new Exception( esc_html__( 'Unauthorized', 'appnatively' ), 401 );
        }

        $request->validate(
            [
                'firstName' => 'required|string|min:3|max:255',
                'lastName'  => 'nullable|string|max:255',
                'phone'     => 'nullable|string|max:255',
            ] 
        );

        $first_name = sanitize_text_field( $request->get_param( 'firstName' ) );
        $last_name  = sanitize_text_field( $request->get_param( 'lastName' ) );
        $phone      = sanitize_text_field( $request->get_param( 'phone' ) );

        wp_update_user(
            [
                'ID'           => $user->ID,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim( "$first_name $last_name" ),
            ] 
        );

        if ( $phone ) {
            update_user_meta( $user->ID, 'billing_phone', $phone );
        }

        return Response::send( $this->transform_user( get_userdata( $user->ID ) ) );
    }

    /**
     * Update user password.
     *
     * @param Request $request
     * @return array
     */
    public function update_password( Request $request ): array {
        $user = $this->get_authenticated_user( $request );

        if ( ! $user ) {
            throw new Exception( esc_html__( 'Unauthorized', 'appnatively' ), 401 );
        }

        $request->validate(
            [
                'newPassword' => 'required|string|min:6',
            ] 
        );

        $new_password = $request->get_param( 'newPassword' );

        wp_set_password( $new_password, $user->ID );

        return Response::send( [ 'success' => true ] );
    }

    /**
     * Generate and store a secure token for a user.
     */
    private function generate_token( $user_id ) {
        $token        = bin2hex( random_bytes( 32 ) );
        $hashed_token = hash( 'sha256', $token );
        
        update_user_meta( $user_id, 'appnatively_auth_token', $hashed_token );
        
        return $token;
    }

    /**
     * Get user by token from request.
     */
    private function get_authenticated_user( Request $request ) {
        $token = $this->get_token_from_header( $request );

        if ( ! $token ) {
            return null;
        }

        $hashed_token = hash( 'sha256', $token );

        $users = get_users(
            [
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_key'    => 'appnatively_auth_token',
                //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'meta_value'  => $hashed_token,
                'number'      => 1,
                'count_total' => false,
            ] 
        );

        return ! empty( $users ) ? $users[0] : null;
    }

    /**
     * Extract token from Authorization header.
     */
    private function get_token_from_header( Request $request ) {
        $auth_header = $request->get_header( 'Authorization' );
        if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Transform WP_User to unified array.
     */
    private function transform_user( WP_User $user ) {
        return [
            'id'          => $user->ID,
            'email'       => $user->user_email,
            'firstName'   => $user->first_name,
            'lastName'    => $user->last_name,
            'displayName' => $user->display_name,
            'phone'       => get_user_meta( $user->ID, 'billing_phone', true ),
            'avatarUrl'   => get_avatar_url( $user->ID ),
            'createdAt'   => $user->user_registered,
        ];
    }
}

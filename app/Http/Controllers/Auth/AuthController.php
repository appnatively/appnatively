<?php

namespace Crafium\AppNatively\App\Http\Controllers\Auth;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Helpers\Helpers;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use WP_User;

class AuthController extends Controller {
    /**
     * Failed sign-in attempts allowed from one address before the endpoint
     * starts refusing, and the window those attempts are counted over.
     */
    const LOGIN_MAX_ATTEMPTS = 8;
    const LOGIN_WINDOW       = 900; // 15 minutes.

    /**
     * Caps for the two endpoints that act on someone else's account without
     * the caller proving anything: a password reset sends mail to an address
     * of the caller's choosing, and registration creates a record.
     */
    const RESET_MAX_ATTEMPTS    = 5;
    const RESET_WINDOW          = 900;  // 15 minutes.
    const REGISTER_MAX_ATTEMPTS = 5;
    const REGISTER_WINDOW       = 3600; // 1 hour.

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

        $this->guard_login_attempts();

        $user = wp_authenticate( sanitize_email( $request->get_param( 'email' ) ), $request->get_param( 'password' ) );

        if ( is_wp_error( $user ) ) {
            $this->record_failed_login();
            throw new Exception( esc_html__( 'The provided login credentials are invalid.', 'appnatively' ), 401 );
        }

        $this->clear_failed_logins();

        return Response::send(
            [
                'token' => Auth::issue( $user->ID ),
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

        $this->throttle( 'register', self::REGISTER_MAX_ATTEMPTS, self::REGISTER_WINDOW );

        $request->validate(
            [
                'email'      => 'required|email|max:255',
                'password'   => 'required|string|min:8|max:255',
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
                'display_name' => trim( "{$first_name} {$last_name}" ),
            ]
        );

        $user = get_userdata( $user_id );

        return Response::send(
            [
                'token' => Auth::issue( $user_id ),
                'user'  => $this->transform_user( $user ),
            ]
        );
    }

    /**
     * Get current authenticated user.
     *
     * Authentication is settled by the `auth` middleware before this runs.
     *
     * @param Request $request
     * @return array
     */
    public function me( Request $request ): array {
        return Response::send( $this->transform_user( $this->current_user() ) );
    }

    /**
     * Generate a short-lived, one-time-use autologin token for WebView checkout.
     *
     * The token is stored SHA-256-hashed in a transient, is redeemable exactly
     * once, and is deleted before the auth cookie is set. Because it
     * necessarily travels in a URL — and so ends up in access logs, referrers
     * and browser history — the window is kept to a minute, which is far more
     * than a hand-off to a WebView needs.
     *
     * @param Request $request
     * @return array
     */
    public function autologin_token( Request $request ): array {
        $user = $this->current_user();

        $token = bin2hex( random_bytes( 32 ) );

        set_transient(
            'craf_appna_autologin_' . hash( 'sha256', $token ),
            [ 'user_id' => $user->ID ],
            (int) apply_filters( 'craf_appna_autologin_ttl', MINUTE_IN_SECONDS )
        );

        return Response::send( [ 'craf_appna_token' => $token ] );
    }

    /**
     * Log out, revoking the token this request presented.
     *
     * @param Request $request
     * @return array
     */
    public function logout( Request $request ): array {
        $token = Auth::get_token_from_request( $request );

        if ( $token ) {
            Auth::revoke( $token );
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

        // Each call sends mail, to an address the caller picks, using the
        // site's own mail configuration. Without a cap this is a way to have
        // the site flood someone's inbox and burn its sending reputation.
        $this->throttle( 'reset', self::RESET_MAX_ATTEMPTS, self::RESET_WINDOW );

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
        $user = $this->current_user();

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
                'display_name' => trim( "{$first_name} {$last_name}" ),
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
     * Requires the current password: holding a token is not on its own proof
     * that the caller is the account owner. On success every other token for
     * the account is revoked, so a password change locks out any session the
     * owner did not initiate — and a replacement token is returned so the
     * caller that made the change stays signed in.
     *
     * @param Request $request
     * @return array
     */
    public function update_password( Request $request ): array {
        $user = $this->current_user();

        $request->validate(
            [
                'currentPassword' => 'required|string|max:255',
                'newPassword'     => 'required|string|min:8|max:255',
            ]
        );

        if ( ! wp_check_password( $request->get_param( 'currentPassword' ), $user->user_pass, $user->ID ) ) {
            throw new Exception( esc_html__( 'The current password is incorrect.', 'appnatively' ), 403 );
        }

        wp_set_password( $request->get_param( 'newPassword' ), $user->ID );

        Auth::revoke_all( $user->ID );

        return Response::send(
            [
                'success' => true,
                'token'   => Auth::issue( $user->ID ),
            ]
        );
    }

    /**
     * The user established by the `auth` middleware.
     *
     * @return WP_User
     * @throws Exception If the route was reached without authentication.
     */
    private function current_user(): WP_User {
        $user = wp_get_current_user();

        if ( ! $user instanceof WP_User || ! $user->ID ) {
            throw new Exception( esc_html__( 'Unauthorized', 'appnatively' ), 401 );
        }

        return $user;
    }

    /**
     * Count this request against a per-address budget and refuse once spent.
     *
     * Unlike the sign-in counter, this counts every call rather than only
     * failures — for these endpoints a "successful" call is exactly the abuse.
     *
     * @param string $bucket The action being limited.
     * @param int    $max    Calls allowed in the window.
     * @param int    $window Window length in seconds.
     * @return void
     * @throws Exception
     */
    private function throttle( string $bucket, int $max, int $window ): void {
        $key   = 'craf_appna_rl_' . $bucket . '_' . md5( (string) Helpers::get_user_ip_address() );
        $count = (int) get_transient( $key );

        if ( $count >= $max ) {
            throw new Exception(
                esc_html__( 'Too many requests. Please try again later.', 'appnatively' ),
                429
            );
        }

        set_transient( $key, $count + 1, $window );
    }

    /**
     * Refuse further sign-in attempts once an address has failed too often.
     *
     * @return void
     * @throws Exception
     */
    private function guard_login_attempts(): void {
        if ( (int) get_transient( $this->login_attempts_key() ) >= self::LOGIN_MAX_ATTEMPTS ) {
            throw new Exception(
                esc_html__( 'Too many failed sign-in attempts. Please try again later.', 'appnatively' ),
                429
            );
        }
    }

    /**
     * Count a failed sign-in against the caller's address.
     *
     * @return void
     */
    private function record_failed_login(): void {
        $key = $this->login_attempts_key();

        set_transient( $key, (int) get_transient( $key ) + 1, self::LOGIN_WINDOW );
    }

    /**
     * Reset the failure counter after a successful sign-in.
     *
     * @return void
     */
    private function clear_failed_logins(): void {
        delete_transient( $this->login_attempts_key() );
    }

    /**
     * Transient key counting failed sign-ins for the calling address.
     *
     * @return string
     */
    private function login_attempts_key(): string {
        return 'craf_appna_login_' . md5( (string) Helpers::get_user_ip_address() );
    }

    /**
     * Transform WP_User to unified array.
     *
     * @param WP_User $user The user.
     * @return array
     */
    private function transform_user( WP_User $user ): array {
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

<?php

namespace Crafium\AppNatively\App\Providers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\AuthorizationStorageException;
use Crafium\AppNatively\App\Support\CheckoutHandoff;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use WP_User;

class AuthServiceProvider extends Provider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        // Autologin handler for web checkout
        add_action( 'init', [$this, 'handle_autologin'] );
        add_filter( 'sanitize_option_' . Settings::OPTION_ENABLED, [CheckoutHandoff::class, 'prepare_api_value'] );
        add_action(
            'delete_option_' . Settings::OPTION_ENABLED, static function (): void {
                CheckoutHandoff::prepare_api_value( 'yes' );
            }
        );
        add_action( CheckoutHandoff::CLEANUP_HOOK, [CheckoutHandoff::class, 'cleanup'] );
        add_action(
            'init', static function (): void {
                if ( ! wp_next_scheduled( CheckoutHandoff::CLEANUP_HOOK ) ) {
                    wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', CheckoutHandoff::CLEANUP_HOOK );
                }
            }
        );

        // A credential change has to invalidate app tokens too. Without these,
        // "change your password to lock them out" would not work: WordPress
        // clears its own sessions but knows nothing about ours.
        add_action( 'after_password_reset', [$this, 'revoke_on_password_reset'], 10, 1 );
        add_action( 'profile_update', [$this, 'revoke_on_profile_update'], 10, 2 );
    }

    /**
     * Redeem a one-time autologin token issued to the mobile app.
     *
     * @return void
     */
    public function handle_autologin(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['craf_appna_token'] ) || is_user_logged_in() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token = sanitize_text_field( wp_unslash( $_GET['craf_appna_token'] ) );
        try {
            $redeemed = CheckoutHandoff::redeem(
                $token, static function ( int $user ): void {
                    wp_set_auth_cookie( $user );
                }
            );
        } catch ( AuthorizationStorageException $error ) {
            error_log( 'AppNatively checkout authorization unavailable.' );
            return;
        }
        if ( ! $redeemed ) {
            return;
        }
        wp_safe_redirect( remove_query_arg( 'craf_appna_token' ) );
        exit;
    }

    /**
     * Revoke every app token after a password reset.
     *
     * @param WP_User|mixed $user The user whose password was reset.
     * @return void
     */
    public function revoke_on_password_reset( $user ): void {
        if ( $user instanceof WP_User ) {
            Auth::revoke_all( $user->ID );
        }
    }

    /**
     * Revoke every app token when a profile update changed the password.
     *
     * @param int           $user_id        The updated user.
     * @param WP_User|mixed $old_user_data  The user record before the update.
     * @return void
     */
    public function revoke_on_profile_update( $user_id, $old_user_data ): void {
        if ( ! $old_user_data instanceof WP_User ) {
            return;
        }

        $user = get_userdata( (int) $user_id );

        if ( $user instanceof WP_User && $user->user_pass !== $old_user_data->user_pass ) {
            Auth::revoke_all( (int) $user_id );
        }
    }
}

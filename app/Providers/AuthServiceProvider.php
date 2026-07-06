<?php

namespace Crafium\AppNatively\App\Providers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\Contracts\Provider;

class AuthServiceProvider extends Provider {
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
        // Autologin handler for web checkout
        add_action( 'init', [$this, 'handle_autologin'] );
    }

    public function handle_autologin(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['craf_appna_token'] ) && ! is_user_logged_in() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $token         = sanitize_text_field( wp_unslash( $_GET['craf_appna_token'] ) );
            $hashed_token  = hash( 'sha256', $token );
            $transient_key = 'craf_appna_autologin_' . $hashed_token;

            // One-time-use: read and immediately delete the transient
            $user_id = get_transient( $transient_key );

            if ( $user_id ) {
                delete_transient( $transient_key ); // Invalidate immediately — cannot be replayed
                wp_set_auth_cookie( (int) $user_id );
                wp_safe_redirect( remove_query_arg( 'craf_appna_token' ) );
                exit;
            }
        }
    }
}

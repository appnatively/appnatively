<?php

namespace Crafium\AppNatively\App\Providers\Admin;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\ContentTypes;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\WpMVC\Contracts\Provider;
use Crafium\AppNatively\WpMVC\View\View;

class MenuServiceProvider extends Provider
{
    const PAGE_SLUG = 'craf_appna';
    const NONCE     = 'craf_appna_settings';

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        add_action( 'admin_menu', [$this, 'action_admin_menu'] );
        add_action( 'admin_post_craf_appna_save_settings', [$this, 'handle_save'] );
        add_action( 'admin_post_craf_appna_rotate_key', [$this, 'handle_rotate_key'] );
        add_action( 'admin_post_craf_appna_revoke_tokens', [$this, 'handle_revoke_tokens'] );
    }

    /**
     * Action to register the admin menu and submenus.
     *
     * @return void
     */
    public function action_admin_menu() {
        add_menu_page(
            __( 'AppNatively', 'appnatively' ),
            __( 'AppNatively', 'appnatively' ),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'dashboard'],
            'dashicons-smartphone',
            30
        );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'appnatively' ) );
        }

        View::render(
            'settings', [
                'api_enabled'    => Settings::is_api_enabled(),
                'site_key'       => Settings::get_site_key(),
                'site_url'       => home_url(),
                'active_tokens'  => Auth::count_all_active(),
                'token_days'     => (int) round( Auth::get_ttl() / DAY_IN_SECONDS ),
                'permalink_ok'   => Settings::uses_postname_permalinks(),
                'content_types'  => ContentTypes::all(),
                'notice'         => $this->current_notice(),
            ]
        );
    }

    /**
     * Save the settings form.
     *
     * @return void
     */
    public function handle_save() {
        $this->authorize();

        // authorize() above ran check_admin_referer() and the capability check;
        // PHPCS cannot follow that through a method call.
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
        Settings::set_api_enabled( isset( $_POST['api_enabled'] ) );

        $this->redirect_back( 'saved' );
    }

    /**
     * Issue a new site connection key.
     *
     * @return void
     */
    public function handle_rotate_key() {
        $this->authorize();

        Settings::regenerate_site_key();

        $this->redirect_back( 'key-rotated' );
    }

    /**
     * Revoke every issued app token.
     *
     * @return void
     */
    public function handle_revoke_tokens() {
        $this->authorize();

        Auth::revoke_everyones();

        $this->redirect_back( 'tokens-revoked' );
    }

    /**
     * Confirm the request came from an administrator who submitted the form.
     *
     * @return void
     */
    private function authorize(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'appnatively' ), 403 );
        }

        check_admin_referer( self::NONCE );
    }

    /**
     * Send the administrator back to the settings screen with a result flag.
     *
     * @param string $result The result slug.
     * @return void
     */
    private function redirect_back( string $result ): void {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page'              => self::PAGE_SLUG,
                    'craf_appna_result' => $result,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * The message to show for the result flag on the current request.
     *
     * @return string
     */
    private function current_notice(): string {
        //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $result = isset( $_GET['craf_appna_result'] ) ? sanitize_key( wp_unslash( $_GET['craf_appna_result'] ) ) : '';

        $messages = [
            'saved'          => __( 'Settings saved.', 'appnatively' ),
            'key-rotated'    => __( 'A new connection key was issued. Paste it into AppNatively Studio — the previous key no longer works.', 'appnatively' ),
            'tokens-revoked' => __( 'All app sessions were signed out. People using the app will need to sign in again.', 'appnatively' ),
        ];

        return $messages[ $result ] ?? '';
    }
}

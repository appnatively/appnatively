<?php
/**
 * AppNatively settings screen.
 *
 * @var bool   $api_enabled
 * @var string $site_key
 * @var string $site_url
 * @var int    $active_tokens
 * @var int    $token_days
 * @var bool   $permalink_ok
 * @var string $notice
 */

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Providers\Admin\MenuServiceProvider;
?>

<div class="wrap">
    <h1><?php esc_html_e( 'AppNatively', 'appnatively' ); ?></h1>

    <?php if ( $notice ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $notice ); ?></p>
        </div>
    <?php endif; ?>

    <p class="description" style="max-width: 48em;">
        <?php esc_html_e( 'This plugin connects your WordPress website with AppNatively, the platform for building native Android and iOS apps. It provides the APIs AppNatively uses to access your website content, products, orders, forms, and supported integrations.', 'appnatively' );?>
    </p>

    <div
        class="notice notice-info inline"
        style="max-width: 48em; margin-top: 20px; margin-bottom: 24px;"
    >
        <p>
            <strong>
                <?php esc_html_e( 'Ready to turn your WordPress website into a mobile app?', 'appnatively' ); ?>
            </strong>
        </p>

        <p>
            <?php
            esc_html_e(
                'Build and manage your native Android and iOS app with AppNatively Studio. After creating your AppNatively account, connect this WordPress website using the connection key shown below.',
                'appnatively'
            );
            ?>
        </p>

        <p>
            <a
                href="<?php echo esc_url( 'https://appnatively.com?utm_source=appnatively_plugin' ); ?>"
                class="button button-primary"
                target="_blank"
                rel="noopener noreferrer"
            >
                <?php esc_html_e( 'Visit AppNatively', 'appnatively' ); ?>

                <span
                    class="dashicons dashicons-external"
                    style="font-size: 16px; line-height: 28px; margin-left: 3px;"
                    aria-hidden="true"
                ></span>
            </a>

            <a
                href="<?php echo esc_url( 'https://appnatively.com?utm_source=appnatively_plugin' ); ?>"
                target="_blank"
                rel="noopener noreferrer"
                style="margin-left: 10px;"
            >
                appnatively.com
            </a>
        </p>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="craf_appna_save_settings">
        <?php wp_nonce_field( MenuServiceProvider::NONCE ); ?>

        <h2><?php esc_html_e( 'API', 'appnatively' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Status', 'appnatively' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="api_enabled" value="1" <?php checked( $api_enabled ); ?>>
                        <?php esc_html_e( 'Answer requests from the mobile app', 'appnatively' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Turn this off to stop every AppNatively endpoint without deactivating the plugin or losing these settings.', 'appnatively' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Address', 'appnatively' ); ?></th>
                <td>
                    <code><?php echo esc_html( $site_url ); ?></code>
                    <?php if ( ! $permalink_ok ) : ?>
                    <p class="description"><?php esc_html_e( 'Requires the "Post name" permalink structure.', 'appnatively' ); ?></p>
                        <p class="description" style="color: #b32d2e;">
                            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                            <?php
                            printf(
                                /* translators: 1: opening link tag to the Permalinks settings screen, 2: closing link tag */
                                esc_html__( 'Your permalink structure is not set to "Post name". %1$sUpdate your permalink settings%2$s so links this API returns for your content resolve correctly.', 'appnatively' ),
                                '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">',
                                '</a>'
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr>

    <h2><?php esc_html_e( 'Studio connection', 'appnatively' ); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e( 'Connection key', 'appnatively' ); ?></th>
            <td>
                <input type="text" class="large-text code" readonly
                    onfocus="this.select()"
                    value="<?php echo esc_attr( $site_key ); ?>">
                <p class="description" style="max-width: 42em;">
                    <?php esc_html_e( 'Paste this into AppNatively Studio to let it read which of your plugins are active. Treat it like a password — anyone holding it can see your active integrations.', 'appnatively' ); ?>
                </p>
            </td>
        </tr>
    </table>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
        onsubmit="return confirm('<?php echo esc_js( __( 'Issue a new key? Studio will lose access until you paste the new one in.', 'appnatively' ) ); ?>');">
        <input type="hidden" name="action" value="craf_appna_rotate_key">
        <?php wp_nonce_field( MenuServiceProvider::NONCE ); ?>
        <?php submit_button( __( 'Issue a new key', 'appnatively' ), 'secondary', 'submit', false ); ?>
    </form>

    <hr>

    <h2><?php esc_html_e( 'App sessions', 'appnatively' ); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e( 'Signed in now', 'appnatively' ); ?></th>
            <td>
                <p>
                    <?php
                    printf(
                        /* translators: 1: number of active sessions, 2: number of days a session lasts */
                        esc_html( _n( '%1$s active session. Sessions expire after %2$s days.', '%1$s active sessions. Sessions expire after %2$s days.', $active_tokens, 'appnatively' ) ),
                        esc_html( number_format_i18n( $active_tokens ) ),
                        esc_html( number_format_i18n( $token_days ) )
                    );
                    ?>
                </p>
                <p class="description" style="max-width: 42em;">
                    <?php esc_html_e( 'Signing everyone out revokes every access token the app has issued. Changing an account password already signs that one account out.', 'appnatively' ); ?>
                </p>
            </td>
        </tr>
    </table>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
        onsubmit="return confirm('<?php echo esc_js( __( 'Sign out every app user? They will each need to sign in again.', 'appnatively' ) ); ?>');">
        <input type="hidden" name="action" value="craf_appna_revoke_tokens">
        <?php wp_nonce_field( MenuServiceProvider::NONCE ); ?>
        <?php submit_button( __( 'Sign out all app users', 'appnatively' ), 'delete', 'submit', false ); ?>
    </form>
</div>

<?php
/**
 * Site-owner controlled settings for the mobile app bridge.
 *
 * @package Crafium\AppNatively\App\Support
 */

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Wraps the handful of options that let a site owner see and control what this
 * plugin exposes: whether the API answers at all, and the connection key that
 * authorises AppNatively Studio to read the site's integration list.
 */
class Settings {
    const OPTION_ENABLED  = 'craf_appna_api_enabled';
    const OPTION_SITE_KEY = 'craf_appna_site_key';

    /**
     * Whether the REST bridge should answer requests.
     *
     * Defaults to enabled so activation is not a two-step process, but the
     * owner can switch the whole surface off from the settings screen without
     * deactivating the plugin.
     *
     * @return bool
     */
    public static function is_api_enabled(): bool {
        return (bool) apply_filters( 'craf_appna_api_enabled', 'no' !== get_option( self::OPTION_ENABLED, 'yes' ) );
    }

    /**
     * Set whether the REST bridge answers requests.
     *
     * @param bool $enabled Whether to enable.
     * @return void
     */
    public static function set_api_enabled( bool $enabled ): void {
        update_option( self::OPTION_ENABLED, $enabled ? 'yes' : 'no' );
    }

    /**
     * Get the site connection key, generating one on first use.
     *
     * @return string
     */
    public static function get_site_key(): string {
        $key = get_option( self::OPTION_SITE_KEY, '' );

        if ( ! is_string( $key ) || strlen( $key ) < 32 ) {
            $key = self::regenerate_site_key();
        }

        return $key;
    }

    /**
     * Issue a fresh site connection key, invalidating the previous one.
     *
     * @return string
     */
    public static function regenerate_site_key(): string {
        $key = bin2hex( random_bytes( 24 ) );

        update_option( self::OPTION_SITE_KEY, $key, false );

        return $key;
    }

    /**
     * Verify a presented connection key in constant time.
     *
     * Reads the stored option directly rather than going through
     * get_site_key(), which mints a key when none exists — an anonymous caller
     * guessing at the header should not be able to cause an option write, and
     * a site with no key yet should reject every candidate rather than
     * generating one to compare against.
     *
     * @param string|null $candidate The key supplied by the caller.
     * @return bool
     */
    public static function verify_site_key( ?string $candidate ): bool {
        if ( empty( $candidate ) || ! is_string( $candidate ) ) {
            return false;
        }

        $stored = get_option( self::OPTION_SITE_KEY, '' );

        if ( ! is_string( $stored ) || strlen( $stored ) < 32 ) {
            return false;
        }

        return hash_equals( $stored, $candidate );
    }

    /**
     * The refusal returned while the bridge is switched off.
     *
     * Shared so the gate filter and each middleware answer identically — the
     * middleware refuse on their own rather than relying on the gate running.
     *
     * @return \WP_Error
     */
    public static function api_disabled_error(): \WP_Error {
        return new \WP_Error(
            'craf_appna_api_disabled',
            __( 'The AppNatively API is turned off for this site.', 'appnatively' ),
            [ 'status' => 503 ]
        );
    }

    /**
     * Whether the site's permalink structure is (or is built on) "Post name".
     *
     * The app resolves content through URLs built from %postname%; a site left
     * on "Plain", or on a structure with no post name token, still answers API
     * requests, but every link surfaced to the app for that content breaks.
     *
     * @return bool
     */
    public static function uses_postname_permalinks(): bool {
        $structure = get_option( 'permalink_structure', '' );

        return is_string( $structure ) && false !== strpos( $structure, '%postname%' );
    }

    /**
     * Remove every option this plugin owns. Used on uninstall.
     *
     * @return void
     */
    public static function delete_all(): void {
        delete_option( self::OPTION_ENABLED );
        delete_option( self::OPTION_SITE_KEY );
        delete_option( ContentTypes::OPTION );

        // Legacy option from the removed reverse-proxy feature; still cleaned
        // up here for sites that had it set before it was removed.
        delete_option( 'craf_appna_trusted_proxies' );
    }
}

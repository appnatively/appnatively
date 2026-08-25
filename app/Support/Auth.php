<?php
/**
 * Bearer token issuance, verification and revocation for the mobile app.
 *
 * @package Crafium\AppNatively\App\Support
 */

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_User;

/**
 * Class Auth
 *
 * Tokens are issued as "{user_id}.{secret}". Only a SHA-256 hash of the secret
 * is ever stored, alongside its issue and expiry timestamps, so a database
 * leak does not yield usable credentials. Embedding the user id lets a token
 * be verified with a single meta read instead of a site-wide meta_value query.
 */
class Auth {
    /**
     * Meta key holding a user's active token hashes.
     *
     * Underscore-prefixed so WordPress treats it as protected meta and it is
     * not exposed or editable through generic custom-field interfaces.
     */
    const META_KEY = '_craf_appna_auth_tokens';

    /**
     * Default token lifetime in seconds.
     */
    const DEFAULT_TTL = 30 * DAY_IN_SECONDS;

    /**
     * Maximum concurrent tokens retained per user. Oldest are dropped first.
     */
    const MAX_TOKENS = 10;

    /**
     * Whether the current request was authenticated by one of our tokens
     * rather than by a WordPress login cookie.
     *
     * @var bool
     */
    private static bool $token_authenticated = false;

    /**
     * Get the configured token lifetime.
     *
     * @return int Seconds.
     */
    public static function get_ttl(): int {
        /**
         * Filters how long an issued app token remains valid.
         *
         * @param int $ttl Lifetime in seconds.
         */
        $ttl = (int) apply_filters( 'craf_appna_auth_token_ttl', self::DEFAULT_TTL );

        return $ttl > 0 ? $ttl : self::DEFAULT_TTL;
    }

    /**
     * Issue a new token for a user.
     *
     * @param int $user_id The user to issue for.
     * @return string The plaintext token. Never stored, never recoverable.
     */
    public static function issue( int $user_id ): string {
        $secret = bin2hex( random_bytes( 32 ) );
        $now    = time();

        $tokens = self::get_tokens( $user_id );

        $tokens[ hash( 'sha256', $secret ) ] = [
            'issued'  => $now,
            'expires' => $now + self::get_ttl(),
        ];

        // Keep the newest MAX_TOKENS so a user signing in across many devices
        // does not accumulate credentials indefinitely.
        if ( count( $tokens ) > self::MAX_TOKENS ) {
            uasort(
                $tokens, function ( $a, $b ) {
                    return $b['issued'] <=> $a['issued'];
                }
            );
            $tokens = array_slice( $tokens, 0, self::MAX_TOKENS, true );
        }

        update_user_meta( $user_id, self::META_KEY, $tokens );

        return $user_id . '.' . $secret;
    }

    /**
     * Resolve the user id a request's Bearer token belongs to, without
     * changing the current user.
     *
     * @param WP_REST_Request $request The current request.
     * @return int|null
     */
    public static function resolve( WP_REST_Request $request ): ?int {
        $token = self::get_token_from_request( $request );

        if ( ! $token || false === strpos( $token, '.' ) ) {
            return null;
        }

        [ $user_id, $secret ] = explode( '.', $token, 2 );

        $user_id = (int) $user_id;

        if ( $user_id < 1 || '' === $secret ) {
            return null;
        }

        $tokens = self::get_tokens( $user_id );

        if ( empty( $tokens ) ) {
            return null;
        }

        $candidate = hash( 'sha256', $secret );
        $now       = time();

        foreach ( $tokens as $stored_hash => $meta ) {
            if ( ! hash_equals( (string) $stored_hash, $candidate ) ) {
                continue;
            }

            if ( empty( $meta['expires'] ) || $meta['expires'] < $now ) {
                self::forget( $user_id, (string) $stored_hash );
                return null;
            }

            return get_userdata( $user_id ) instanceof WP_User ? $user_id : null;
        }

        return null;
    }

    /**
     * Authenticate the request's Bearer token and set it as the current user.
     *
     * No-ops if a user is already established for this request.
     *
     * @param WP_REST_Request $request The current request.
     * @return int|null The authenticated user id.
     */
    public static function authenticate( WP_REST_Request $request ): ?int {
        if ( get_current_user_id() ) {
            return get_current_user_id();
        }

        $user_id = self::resolve( $request );

        if ( ! $user_id ) {
            return null;
        }

        wp_set_current_user( $user_id );
        self::$token_authenticated = true;

        return $user_id;
    }

    /**
     * Whether the current request was authenticated by an app token.
     *
     * A token in an Authorization header cannot be attached by a browser on a
     * user's behalf, so such a request carries no cross-site request forgery
     * risk. A cookie-authenticated request does, and must not be treated the
     * same way.
     *
     * @return bool
     */
    public static function is_token_authenticated(): bool {
        return self::$token_authenticated;
    }

    /**
     * Revoke a single token.
     *
     * @param string $token The plaintext token.
     * @return void
     */
    public static function revoke( string $token ): void {
        if ( false === strpos( $token, '.' ) ) {
            return;
        }

        [ $user_id, $secret ] = explode( '.', $token, 2 );

        self::forget( (int) $user_id, hash( 'sha256', $secret ) );
    }

    /**
     * Revoke every token belonging to a user.
     *
     * @param int $user_id The user.
     * @return void
     */
    public static function revoke_all( int $user_id ): void {
        delete_user_meta( $user_id, self::META_KEY );
    }

    /**
     * Count a user's unexpired tokens.
     *
     * @param int $user_id The user.
     * @return int
     */
    public static function count_active( int $user_id ): int {
        return count( self::get_tokens( $user_id ) );
    }

    /**
     * Count unexpired tokens across every user. Used by the settings screen.
     *
     * Reads the stored rows in one query and counts in PHP rather than calling
     * get_user_meta() per user, which turned this into an N+1 on every render
     * of the settings page.
     *
     * @return int
     */
    public static function count_all_active(): int {
        global $wpdb;

        //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_col(
            $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", self::META_KEY )
        );

        $now   = time();
        $total = 0;

        foreach ( (array) $rows as $row ) {
            $tokens = maybe_unserialize( $row );

            if ( ! is_array( $tokens ) ) {
                continue;
            }

            foreach ( $tokens as $meta ) {
                if ( is_array( $meta ) && ! empty( $meta['expires'] ) && $meta['expires'] >= $now ) {
                    ++$total;
                }
            }
        }

        return $total;
    }

    /**
     * Revoke every app token on the site.
     *
     * Deletes the meta key outright in one statement. A paged get_users() loop
     * would silently stop at its page size, reporting success while leaving an
     * unbounded number of tokens live — the opposite of what an administrator
     * reaching for this control needs.
     *
     * @return int Number of users whose tokens were cleared.
     */
    public static function revoke_everyones(): int {
        global $wpdb;

        // Collect the affected ids first: after the delete there is no way to
        // know whose meta caches need clearing, and a stale cache would mean a
        // revoked token still validates.
        //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $user_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", self::META_KEY )
        );

        if ( empty( $user_ids ) ) {
            return 0;
        }

        //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bulk-revoking every issued token by this plugin's own meta key, not a WP_Query arg.
        $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => self::META_KEY ], [ '%s' ] );

        // wp_cache_flush_group() is not implemented by every object-cache
        // drop-in, so clear each entry explicitly instead.
        foreach ( $user_ids as $user_id ) {
            wp_cache_delete( (int) $user_id, 'user_meta' );
        }

        return count( $user_ids );
    }

    /**
     * Read a user's stored tokens, dropping any that have expired.
     *
     * @param int $user_id The user.
     * @return array<string, array{issued:int, expires:int}>
     */
    private static function get_tokens( int $user_id ): array {
        $tokens = get_user_meta( $user_id, self::META_KEY, true );

        if ( ! is_array( $tokens ) ) {
            return [];
        }

        $now   = time();
        $fresh = [];

        foreach ( $tokens as $hash => $meta ) {
            if ( is_array( $meta ) && ! empty( $meta['expires'] ) && $meta['expires'] >= $now ) {
                $fresh[ $hash ] = $meta;
            }
        }

        return $fresh;
    }

    /**
     * Remove one token hash from a user's stored set.
     *
     * @param int    $user_id The user.
     * @param string $hash    The stored SHA-256 hash.
     * @return void
     */
    private static function forget( int $user_id, string $hash ): void {
        $tokens = self::get_tokens( $user_id );

        if ( ! isset( $tokens[ $hash ] ) ) {
            return;
        }

        unset( $tokens[ $hash ] );

        if ( empty( $tokens ) ) {
            delete_user_meta( $user_id, self::META_KEY );
            return;
        }

        update_user_meta( $user_id, self::META_KEY, $tokens );
    }

    /**
     * Extract the Bearer token from the Authorization header.
     *
     * @param WP_REST_Request $request The current request.
     * @return string|null
     */
    public static function get_token_from_request( WP_REST_Request $request ): ?string {
        $header = $request->get_header( 'Authorization' );

        if ( $header && preg_match( '/Bearer\s+(\S+)/i', $header, $matches ) ) {
            return $matches[1];
        }

        return null;
    }
}

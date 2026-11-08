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
        return AuthorizationStore::locked(
            static function () use ( $user_id ): string {
                $secret   = bin2hex( random_bytes( 32 ) );
                $now      = time();
                $row      = AuthorizationStore::token_row( $user_id );
                $previous = AuthorizationStore::decode_tokens( $row );
                $tokens   = self::fresh_tokens( $previous );
                if ( count( $tokens ) >= self::MAX_TOKENS ) {
                    uksort(
                        $tokens, static function ( $a, $b ) use ( $tokens ): int {
                            return ( $tokens[$b]['issued'] <=> $tokens[$a]['issued'] ) ?: strcmp( $a, $b );
                        }
                    );
                    $tokens = array_slice( $tokens, 0, self::MAX_TOKENS - 1, true );
                }
                $tokens[hash( 'sha256', $secret )] = [
                    'issued'  => $now,
                    'expires' => $now + self::get_ttl(),
                ];
                AuthorizationStore::save_tokens( $user_id, $row, $tokens );
                self::changed( $user_id, $previous, $tokens );
                return $user_id . '.' . $secret;
            }
        );
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
        AuthorizationStore::locked(
            static function () use ( $user_id ): void {
                $row      = AuthorizationStore::token_row( $user_id );
                $previous = AuthorizationStore::decode_tokens( $row );
                AuthorizationStore::advance( 'craf_appna_auth_user_' . $user_id );
                if ( $row ) {
                    AuthorizationStore::save_tokens( $user_id, $row, [] );
                    self::changed( $user_id, $previous, [] );
                }
            }
        );
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
        return AuthorizationStore::locked(
            static function (): int {
                $total = 0;
                foreach ( self::all_token_rows() as $row ) {
                    $total += count( self::fresh_tokens( AuthorizationStore::decode_tokens( $row ) ) );
                }
                return $total;
            }
        );
    }

    /**
     * Revoke every app token on the site.
     *
     * Clears current snapshots under the same lock as issuance. Empty rows
     * remain as tombstones; no paged user list can omit active credentials.
     *
     * @return int Number of users whose tokens were cleared.
     */
    public static function revoke_everyones(): int {
        return AuthorizationStore::locked(
            static function (): int {
                $rows    = self::all_token_rows();
                $cleared = 0;
                AuthorizationStore::advance( 'craf_appna_auth_global' );
                foreach ( $rows as $row ) {
                    $previous = AuthorizationStore::decode_tokens( $row );
                    AuthorizationStore::save_tokens( (int) $row['user_id'], $row, [] );
                    if ( $previous ) {
                        ++$cleared;
                        self::changed( (int) $row['user_id'], $previous, [] );
                    }
                }
                return $cleared;
            }
        );
    }

    private static function all_token_rows(): array {
        global $wpdb;
        $rows = AuthorizationStore::rows( $wpdb->prepare( "SELECT umeta_id,user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s ORDER BY user_id", self::META_KEY ) );
        $seen = [];
        foreach ( $rows as $row ) {
            if ( isset( $seen[$row['user_id']] ) ) {
                throw new AuthorizationStorageException();
            }
            AuthorizationStore::decode_tokens( $row );
            $seen[$row['user_id']] = true;
        }
        return $rows;
    }

    /** Shared authoritative reader for authentication and notification proofs. */
    public static function get_tokens( int $user_id ): array {
        return self::fresh_tokens( AuthorizationStore::decode_tokens( AuthorizationStore::token_row( $user_id ) ) );
    }

    private static function fresh_tokens( array $tokens ): array {
        $now = time();
        return array_filter(
            $tokens, static function ( $meta ) use ( $now ): bool {
                return is_array( $meta ) && ( $meta['expires'] ?? 0 ) > $now;
            }
        );
    }

    private static function forget( int $user_id, string $hash ): void {
        AuthorizationStore::locked(
            static function () use ( $user_id, $hash ): void {
                $row      = AuthorizationStore::token_row( $user_id );
                $previous = AuthorizationStore::decode_tokens( $row );
                if ( ! isset( $previous[$hash] ) ) {
                    return;
                }
                $tokens = self::fresh_tokens( $previous );
                unset( $tokens[$hash] );
                AuthorizationStore::save_tokens( $user_id, $row, $tokens );
                self::changed( $user_id, $previous, $tokens );
            }
        );
    }

    private static function changed( int $user, array $previous, array $tokens ): void {
        try {
            do_action( 'craf_appna_auth_tokens_changed', $user, $previous, $tokens );
        } catch ( \Throwable $error ) {
            // A notification extension cannot roll back a completed account action.
            error_log( 'AppNatively token-change notification failed.' );
        }
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

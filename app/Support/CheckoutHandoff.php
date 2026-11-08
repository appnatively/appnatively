<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

/** A short-lived exchange of an existing credential, never an independent login. */
class CheckoutHandoff {
    const PREFIX         = 'craf_appna_handoff_';
    const EXPIRY_PREFIX  = 'craf_appna_handoff_expiry_';
    const API_GENERATION = 'craf_appna_auth_api';
    const CLEANUP_HOOK   = 'craf_appna_handoff_cleanup';

    public static function issue( \WP_REST_Request $request, int $user ): string {
        return AuthorizationStore::locked(
            static function () use ( $request, $user ): string {
                global $wpdb;
                AuthorizationStore::require_autocommit();
                if ( ! self::enabled() ) {
                    throw new AuthorizationStorageException();
                }
                $password = self::password( $user );
                $source   = self::source( $request, $user );
                if ( $password === null || $source === null ) {
                    throw new \RuntimeException( 'A valid originating session is required.', 401 );
                }
                $ttl     = (int) apply_filters( 'craf_appna_autologin_ttl', MINUTE_IN_SECONDS );
                $ttl     = $ttl > 0 ? $ttl : MINUTE_IN_SECONDS;
                $payload = [
                    'version'  => 2,
                    'user_id'  => $user,
                    'expires'  => time() + $ttl,
                    'source'   => $source,
                    'password' => $password,
                    'user'     => AuthorizationStore::generation( 'craf_appna_auth_user_' . $user ),
                    'global'   => AuthorizationStore::generation( 'craf_appna_auth_global' ),
                    'api'      => AuthorizationStore::generation( self::API_GENERATION, $wpdb->options ),
                ];
                $token   = bin2hex( random_bytes( 32 ) );
                $hash    = hash( 'sha256', $token );
                AuthorizationStore::write( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", self::EXPIRY_PREFIX . $hash, (string) $payload['expires'] ) );
                AuthorizationStore::write( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", self::PREFIX . $hash, maybe_serialize( $payload ) ) );
                return $token;
            }
        );
    }

    /** The cookie callback runs only after one successful conditional consume. */
    public static function redeem( string $token, callable $set_cookie ): bool {
        if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $token ) ) {
            return false;
        }
        return AuthorizationStore::locked(
            static function () use ( $token, $set_cookie ): bool {
                global $wpdb;
                AuthorizationStore::require_autocommit();
                AuthorizationStore::write( 'START TRANSACTION', 0 );
                try {
                    if ( ! self::enabled() ) {
                        return false;
                    }
                    $hash = hash( 'sha256', $token );
                    $key  = self::PREFIX . $hash;
                    $row  = AuthorizationStore::option( $key, $wpdb->options );
                    $data = $row ? maybe_unserialize( $row['option_value'] ) : null;
                    if ( ! is_array( $data ) || ( $data['version'] ?? 0 ) !== 2 || ( $data['expires'] ?? 0 ) <= time() ) {
                        return false;
                    }
                    $user = (int) ( $data['user_id'] ?? 0 );
                    if ( ! $user || ! isset( $data['password'], $data['source'], $data['user'], $data['global'], $data['api'] ) ) {
                        return false;
                    }
                    if ( ! hash_equals( $data['password'], (string) self::password( $user ) ) ||
                    ! hash_equals( $data['user'], AuthorizationStore::generation( 'craf_appna_auth_user_' . $user ) ) ||
                    ! hash_equals( $data['global'], AuthorizationStore::generation( 'craf_appna_auth_global' ) ) ||
                    ! hash_equals( $data['api'], AuthorizationStore::generation( self::API_GENERATION, $wpdb->options ) ) ||
                    ! self::source_valid( $data['source'], $user ) ) {
                        return false;
                    }
                    AuthorizationStore::write( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND BINARY option_value=%s", $key, $row['option_value'] ) );
                    self::delete_expiry( $hash );
                    AuthorizationStore::write( 'COMMIT', 0 );
                } finally {
                    // A failed/invalid exchange releases only its own transaction.
                    $wpdb->query( 'ROLLBACK' );
                }
                $set_cookie( $user );
                return true;
            }
        );
    }

    private static function enabled(): bool {
        global $wpdb;
        $row = AuthorizationStore::option( Settings::OPTION_ENABLED, $wpdb->options );
        return ( ! $row || $row['option_value'] !== 'no' ) && Settings::is_api_enabled();
    }

    private static function password( int $user ): ?string {
        global $wpdb;
        $rows = AuthorizationStore::rows( $wpdb->prepare( "SELECT user_pass FROM {$wpdb->users} WHERE ID=%d", $user ) );
        return isset( $rows[0]['user_pass'] ) ? hash_hmac( 'sha256', $rows[0]['user_pass'], wp_salt( 'auth' ) ) : null;
    }

    private static function source( \WP_REST_Request $request, int $user ): ?array {
        $token = Auth::get_token_from_request( $request );
        if ( $token ) {
            if ( ! preg_match( '/\A([1-9][0-9]*)\.([a-f0-9]{64})\z/', $token, $parts ) || (int) $parts[1] !== $user ) {
                return null;
            }
            $source = [
                'type' => 'app',
                'hash' => hash( 'sha256', $parts[2] ),
            ];
        } else {
            $token = wp_get_session_token();
            if ( ! $token || ! \WP_Session_Tokens::get_instance( $user )->verify( $token ) ) {
                return null;
            }
            $source = [
                'type' => 'wordpress',
                'hash' => hash( 'sha256', $token ),
            ];
        }
        return self::source_valid( $source, $user ) ? $source : null;
    }

    private static function source_valid( array $source, int $user ): bool {
        global $wpdb;
        if ( ! isset( $source['hash'], $source['type'] ) || ! is_string( $source['hash'] ) ) {
            return false;
        }
        if ( $source['type'] === 'app' ) {
            return isset( Auth::get_tokens( $user )[$source['hash']] );
        }
        if ( $source['type'] !== 'wordpress' ) {
            return false;
        }
        // Verify the retained WordPress verifier without storing its plaintext token.
        $rows = AuthorizationStore::rows( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='session_tokens'", $user ) );
        if ( count( $rows ) > 1 ) {
            throw new AuthorizationStorageException();
        }
        $sessions = $rows ? maybe_unserialize( $rows[0]['meta_value'] ) : [];
        return is_array( $sessions ) && ( $sessions[$source['hash']]['expiration'] ?? 0 ) > time();
    }

    public static function prepare_api_value( $value ) {
        return AuthorizationStore::locked(
            static function () use ( $value ) {
                global $wpdb;
                $row = AuthorizationStore::option( Settings::OPTION_ENABLED, $wpdb->options );
                // Invalidate on both sides of an off/on transition. Direct WP
                // option writers do not hold our lock through their persistence.
                if ( $value === 'no' || ( $row && $row['option_value'] === 'no' ) ) {
                    AuthorizationStore::advance( self::API_GENERATION, $wpdb->options );
                }
                return $value;
            }
        );
    }

    private static function delete_expiry( string $hash ): void {
        global $wpdb;
        $key = self::EXPIRY_PREFIX . $hash;
        $row = AuthorizationStore::option( $key, $wpdb->options );
        if ( $row ) {
            AuthorizationStore::write( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND BINARY option_value=%s", $key, $row['option_value'] ) );
        }
    }

    public static function cleanup(): void {
        try {
            AuthorizationStore::locked(
                static function (): void {
                    global $wpdb;
                    $rows = AuthorizationStore::rows( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED)<=%d LIMIT 100", $wpdb->esc_like( self::EXPIRY_PREFIX ) . '%', time() ) );
                    foreach ( $rows as $row ) {
                        $hash = substr( $row['option_name'], strlen( self::EXPIRY_PREFIX ) );
                        $key  = self::PREFIX . $hash;
                        $item = AuthorizationStore::option( $key, $wpdb->options );
                        if ( $item ) {
                            AuthorizationStore::write( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND BINARY option_value=%s", $key, $item['option_value'] ) );
                        }
                        self::delete_expiry( $hash );
                    }
                }
            );
        } catch ( AuthorizationStorageException $error ) {
            error_log( 'AppNatively checkout cleanup will retry.' );
        }
    }
}

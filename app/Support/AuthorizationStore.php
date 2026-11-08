<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

/** Current reads and fenced writes; never starts or ends the caller's transaction. */
class AuthorizationStore {
    private static array $locks = [];

    public static function locked( callable $operation ) {
        global $wpdb;
        $connection = spl_object_hash( $wpdb );
        if ( isset( self::$locks[$connection] ) ) {
            self::assert_locked();
            return $operation();
        }
        $database = $wpdb->get_var( 'SELECT DATABASE()' );
        if ( ! is_string( $database ) || $database === '' || $wpdb->last_error ) {
            throw new AuthorizationStorageException();
        }
        $name = 'appna_auth_' . substr( hash( 'sha256', $database . ':' . $wpdb->usermeta ), 0, 48 );
        if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) ) !== '1' ) {
            throw new AuthorizationStorageException();
        }
        self::$locks[$connection] = $name;
        try {
            return $operation();
        } finally {
            unset( self::$locks[$connection] );
            if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ) !== '1' ) {
                throw new AuthorizationStorageException();
            }
        }
    }

    /** Detect wpdb reconnecting and silently losing the connection-owned lock. */
    public static function assert_locked(): void {
        global $wpdb;
        $name = self::$locks[spl_object_hash( $wpdb )] ?? null;
        if ( ! $name || (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)=CONNECTION_ID()', $name ) ) !== '1' ) {
            throw new AuthorizationStorageException();
        }
    }

    public static function rows( string $sql ): array {
        global $wpdb;
        // A locking read also sees current committed state inside REPEATABLE READ.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal callers supply prepared SQL.
        $rows = $wpdb->get_results( $sql . ' FOR UPDATE', ARRAY_A );
        if ( ! is_array( $rows ) || $wpdb->last_error ) {
            throw new AuthorizationStorageException();
        }
        return $rows;
    }

    public static function write( string $sql, int $expected = 1 ): void {
        global $wpdb;
        self::assert_locked();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal callers supply prepared SQL or fixed transaction statements.
        if ( $wpdb->query( $sql ) !== $expected || $wpdb->last_error ) {
            throw new AuthorizationStorageException();
        }
        self::assert_locked();
    }

    /** Cookie exchange cannot expose a success whose consume can later roll back. */
    public static function require_autocommit(): void {
        global $wpdb;
        if ( (string) $wpdb->get_var( 'SELECT @@autocommit' ) !== '1' || ! $wpdb->dbh instanceof \mysqli ) {
            throw new AuthorizationStorageException();
        }
        // SAVEPOINT does not start a transaction or commit caller work. A fresh,
        // random marker exists only when an explicit outer transaction is active.
        $marker = 'appna_probe_' . bin2hex( random_bytes( 12 ) );
        $prior  = $wpdb->suppress_errors( true );
        try {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The identifier is generated from fixed ASCII and random hex.
            $saved = $wpdb->query( 'SAVEPOINT ' . $marker );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Same generated savepoint identifier.
            $released = $wpdb->query( 'RELEASE SAVEPOINT ' . $marker );
            // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_errno -- wpdb does not expose the numeric error needed to distinguish the expected missing savepoint.
            $errno = mysqli_errno( $wpdb->dbh );
        } finally {
            $wpdb->suppress_errors( $prior );
        }
        if ( $saved !== 0 || $released !== false || $errno !== 1305 ) {
            throw new AuthorizationStorageException();
        }
        self::assert_locked();
    }

    public static function token_row( int $user ): ?array {
        global $wpdb;
        $rows = self::rows( $wpdb->prepare( "SELECT umeta_id,meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s", $user, Auth::META_KEY ) );
        if ( count( $rows ) > 1 ) {
            // Merging duplicate snapshots could restore an already-revoked hash.
            throw new AuthorizationStorageException();
        }
        return $rows[0] ?? null;
    }

    public static function decode_tokens( ?array $row ): array {
        if ( ! $row ) {
            return [];
        }
        $tokens = maybe_unserialize( $row['meta_value'] );
        if ( ! is_array( $tokens ) ) {
            throw new AuthorizationStorageException();
        }
        return $tokens;
    }

    public static function save_tokens( int $user, ?array $row, array $tokens ): void {
        global $wpdb;
        $serialized = maybe_serialize( $tokens );
        if ( $row && $serialized === $row['meta_value'] ) {
            return;
        }
        if ( $row ) {
            self::write( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value=%s WHERE umeta_id=%d AND BINARY meta_value=%s", $serialized, $row['umeta_id'], $row['meta_value'] ) );
        } else {
            self::write( $wpdb->prepare( "INSERT INTO {$wpdb->usermeta} (user_id,meta_key,meta_value) VALUES (%d,%s,%s)", $user, Auth::META_KEY, $serialized ) );
        }
        wp_cache_delete( $user, 'user_meta' );
    }

    /** Credentials are network-wide, so their generations must be too. */
    public static function generation_table(): string {
        global $wpdb;
        return $wpdb->base_prefix . 'options';
    }

    public static function option( string $key, string $table ): ?array {
        global $wpdb;
        $rows = self::rows( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name=%s', $table, $key ) );
        return $rows[0] ?? null;
    }

    public static function generation( string $key, ?string $table = null ): string {
        $row = self::option( $key, $table ?? self::generation_table() );
        return $row ? (string) $row['option_value'] : '';
    }

    public static function advance( string $key, ?string $table = null ): void {
        global $wpdb;
        $table = $table ?? self::generation_table();
        $row   = self::option( $key, $table );
        $value = bin2hex( random_bytes( 16 ) );
        if ( $row ) {
            self::write( $wpdb->prepare( "UPDATE %i SET option_value=%s,autoload='no' WHERE option_name=%s AND BINARY option_value=%s", $table, $value, $key, $row['option_value'] ) );
        } else {
            self::write( $wpdb->prepare( "INSERT INTO %i (option_name,option_value,autoload) VALUES (%s,%s,'no')", $table, $key, $value ) );
        }
        wp_cache_delete( $key, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }
}

<?php

namespace Crafium\AppNatively\App\Support;

defined( "ABSPATH" ) || exit;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
use Crafium\AppNatively\App\Models\NotificationBinding;
/** Plugin-owned storage. Atomic counters and fenced writes deliberately use uncached, prepared SQL. */
class NotificationStore
{
    /** Sequence allocation runs in our REST endpoints or background workers only. */
    public static function next_version( string $type, int $id, int $minimum = 0 ): ?int {
        global $wpdb;
        $table  = $wpdb->prefix . 'craf_appna_event_sequences';
        $result = $wpdb->query( $wpdb->prepare( "INSERT INTO %i (entity_type,entity_id,version) VALUES (%s,%d,LAST_INSERT_ID(%d))\n            ON DUPLICATE KEY UPDATE version=LAST_INSERT_ID(GREATEST(version,%d)+1)", $table, $type, $id, $minimum + 1, $minimum ) );
        if ( false === $result ) {
            return null;
        }
        $wpdb->last_error = '';
        $version          = $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
        return ! $wpdb->last_error && is_numeric( $version ) && (int) $version > 0 ? (int) $version : null;
    }

    public static function bindings_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'craf_appna_event_bindings';
    }

    private static function decode( $stored ): array {
        if ( ! is_string( $stored ) || ! function_exists( 'openssl_decrypt' ) ) {
            return [];
        }
        $binary = base64_decode( $stored, true );
        if ( false === $binary || strlen( $binary ) < 29 ) {
            return [];
        }
        $json  = openssl_decrypt( substr( $binary, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, substr( $binary, 0, 12 ), substr( $binary, 12, 16 ) );
        $value = $json ? json_decode( $json, true ) : null;
        return is_array( $value ) ? $value : [];
    }

    private static function encode( array $value ): string {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            throw new \RuntimeException( 'OpenSSL is required to store notification credentials securely.' );
        }
        unset( $value['_storage_version'] );
        $iv        = random_bytes( 12 );
        $tag       = '';
        $encrypted = openssl_encrypt( wp_json_encode( $value ), 'aes-256-gcm', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv, $tag );
        if ( false === $encrypted ) {
            throw new \RuntimeException( 'Notification credentials could not be encrypted.' );
        }
        return base64_encode( $iv . $tag . $encrypted );
    }

    public static function bindings(): array {
        if ( (int) get_option( 'craf_appna_events_schema', 0 ) !== Events::SCHEMA_VERSION ) {
            return [];
        }
        global $wpdb;
        $state      = [
            'insert_id'  => $wpdb->insert_id,
            'last_error' => $wpdb->last_error,
            'last_query' => $wpdb->last_query
        ];
        $suppressed = $wpdb->suppress_errors( true );
        $rows       = [];
        try {
            $wpdb->last_error = '';
            $rows             = NotificationBinding::stored_rows();
            if ( $wpdb->last_error ) {
                throw new \RuntimeException( 'Notification storage unavailable.' );
            }
        } catch ( \Throwable $error ) {
            // The next explicit owner setup can repair our tables; native hooks never migrate.
            delete_option( 'craf_appna_events_schema' );
            Events::record_error( 'notification_storage_unavailable' );
        } finally {
            $wpdb->suppress_errors( $suppressed );
            foreach ( $state as $name => $value ) {
                $wpdb->{$name} = $value;
            }
        }
        $bindings = [];
        foreach ( $rows ?: [] as $row ) {
            $binding = self::decode( $row['payload'] );
            if ( ! $binding || ( $binding['sourceId'] ?? '' ) !== $row['source_id'] || ( $binding['appId'] ?? '' ) !== $row['app_id'] ) {
                continue;
            }
            $binding['_storage_version'] = (int) $row['storage_version'];
            $bindings[$row['source_id']] = $binding;
        }
        return $bindings;
    }

    /** Non-secret metadata permits a fenced repair after stored credentials become unreadable. */
    public static function binding_metadata( string $source ): ?array {
        $binding = NotificationBinding::query()->select(
            [
                "app_id",
                "storage_version"
            ]
        )->where( "source_id", $source )->first();
        return $binding ? [
            "appId"            => $binding->app_id,
            "_storage_version" => (int) $binding->storage_version
        ] : null;
    }

    /** One row per binding, fenced against a stale callback or dispatcher snapshot. */
    public static function save_binding( array $binding, ?array $previous = null ): bool {
        Events::install();
        global $wpdb;
        $table  = self::bindings_table();
        $source = $binding['sourceId'];
        $app    = $binding['appId'] ?? '';
        if ( ! $app || null !== $previous && ( $previous['appId'] ?? '' ) !== $app ) {
            return false;
        }
        $version = $previous['_storage_version'] ?? $binding['_storage_version'] ?? null;
        if ( null === $version ) {
            return 1 === $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (source_id,app_id,payload) VALUES (%s,%s,%s)", $table, $source, $app, self::encode( $binding ) ) );
        }
        return 1 === $wpdb->query( $wpdb->prepare( "UPDATE %i SET payload=%s,storage_version=storage_version+1 WHERE source_id=%s AND app_id=%s AND storage_version=%d", $table, self::encode( $binding ), $source, $app, $version ) );
    }
}

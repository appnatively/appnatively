<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;
use Crafium\AppNatively\App\Jobs\NotificationQueue;
/** Hook payloads are sent exclusively by WpMVC Queue. */
class NotificationEvents
{
    const SCHEMA_VERSION  = 5;
    const CONSENT_VERSION = 1;

    private static array $queues = [];

    public static function allowed(): bool {
        $consent = get_option( 'craf_appna_notification_consent', [] );
        return Settings::is_api_enabled() && 'yes' === get_option( 'craf_appna_notifications_enabled', 'yes' ) && is_array( $consent ) && ( $consent['version'] ?? 0 ) === self::CONSENT_VERSION && ! empty( $consent['epoch'] );
    }

    /** Called only by activation or explicit owner setup, never from native hooks. */
    public static function install(): void {
        if ( (int) get_option( 'craf_appna_events_schema', 0 ) === self::SCHEMA_VERSION ) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table   = self::bindings_table();
        dbDelta( "CREATE TABLE {$table} (\n            source_id varchar(100) NOT NULL,\n            app_id varchar(100) NOT NULL,\n            storage_version bigint unsigned NOT NULL DEFAULT 1,\n            payload longtext NOT NULL,\n            PRIMARY KEY (source_id),\n            KEY app_bindings (app_id)\n        ) ENGINE=InnoDB {$charset};" );
        $sequences = $wpdb->prefix . 'craf_appna_event_sequences';
        dbDelta( "CREATE TABLE {$sequences} (\n            entity_type varchar(32) NOT NULL,\n            entity_id bigint unsigned NOT NULL,\n            version bigint unsigned NOT NULL,\n            PRIMARY KEY (entity_type,entity_id)\n        ) ENGINE=InnoDB {$charset};" );
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sequences ) ) === $sequences ) {
            update_option( 'craf_appna_events_schema', self::SCHEMA_VERSION, false );
        } else {
            delete_option( 'craf_appna_events_schema' );
            self::record_error( 'notification_storage_unavailable' );
        }
    }

    /** Sequence allocation runs in our REST endpoints or background workers only. */
    public static function next_version( string $type, int $id, int $minimum = 0 ): ?int {
        global $wpdb;
        $table  = $wpdb->prefix . 'craf_appna_event_sequences';
        $result = $wpdb->query( $wpdb->prepare( "INSERT INTO %i (entity_type,entity_id,version) VALUES (%s,%d,LAST_INSERT_ID(%d))\n            ON DUPLICATE KEY UPDATE version=LAST_INSERT_ID(GREATEST(version,%d)+1)", $table, $type, $id, $minimum + 1, $minimum ) );
        if ( false === $result ) {
            return null;
        }
        return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
    }

    public static function queue( string $source, string $channel ): NotificationQueue {
        $key = get_current_blog_id() . ':' . $source . ':' . $channel;
        if ( ! isset( self::$queues[$key] ) ) {
            self::$queues[$key] = new NotificationQueue( $source, $channel );
        }
        return self::$queues[$key];
    }

    public static function boot_queues(): void {
        foreach ( self::bindings() as $source => $binding ) {
            self::queue( $source, 'events' );
            self::queue( $source, 'subjects' );
        }
    }

    public static function cancel_queues(): void {
        foreach ( self::bindings() as $source => $binding ) {
            foreach ( [
                'events',
                'subjects',
            ] as $channel ) {
                self::queue( $source, $channel )->discard();
            }
        }
    }

    public static function record_error( string $code ): void {
        try {
            update_option(
                'craf_appna_event_last_error', [
                    'code' => $code,
                    'time' => time(),
                ], false
            );
        } catch ( \Throwable $error ) {
            /* Notification diagnostics cannot break a native action. */
        }
    }

    /** Complete a signed handshake inside the owner's connection request. No outbox or cron. */
    public static function pair_now( array $binding ) {
        if ( ! self::allowed() ) {
            return new \WP_Error(
                'notification_consent_required', __( 'Confirm notification data sharing in WordPress settings first.', 'appnatively' ), [
                    'status' => 403,
                ]
            );
        }
        self::install();
        $binding['pairing_ticket_hash'] = hash( 'sha256', $binding['ticket'] );
        $binding['provisioning']        = 'pairing';
        $bindings                       = self::bindings();
        $previous                       = $bindings[$binding['sourceId']] ?? self::binding_metadata( $binding['sourceId'] );
        // Capture hooks during repair; business dispatch waits for provisioning=ready.
        $binding['enabled'] = ! empty( $previous['enabled'] );
        if ( ! self::save_binding( $binding, $previous ) ) {
            return new \WP_Error(
                'stale_binding', 'Event connection changed. Check connection again.', [
                    'status' => 409,
                ]
            );
        }
        $response = self::request(
            $binding, 'pair', [
                'ticket'       => $binding['ticket'],
                'origin'       => untrailingslashit( home_url() ),
                'restUrl'      => untrailingslashit( rest_url( 'craf_appna/v1' ) ),
                'capabilities' => [
                    'events',
                    'identity',
                    'subjects',
                    'subject-sequence-v1',
                ],
                'definitions'  => self::definitions(),
            ]
        );
        $ready    = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 && ( json_decode( wp_remote_retrieve_body( $response ), true )['paired'] ?? false ) === true;
        $bindings = self::bindings();
        if ( ( $bindings[$binding['sourceId']]['pairing_ticket_hash'] ?? '' ) !== $binding['pairing_ticket_hash'] ) {
            return new \WP_Error(
                'stale_binding', 'Event connection changed.', [
                    'status' => 409,
                ]
            );
        }
        $bindings[$binding['sourceId']]['enabled']      = $ready;
        $bindings[$binding['sourceId']]['provisioning'] = $ready ? 'ready' : 'failed';
        if ( $ready ) {
            unset( $bindings[$binding['sourceId']]['ticket'] );
        }
        if ( ! self::save_binding( $bindings[$binding['sourceId']] ) ) {
            return new \WP_Error(
                'stale_binding', 'Event connection changed.', [
                    'status' => 409,
                ]
            );
        }
        if ( ! $ready ) {
            return new \WP_Error(
                'pairing_failed', 'Signed pairing could not be completed. Check connection again to retry.', [
                    'status' => 502,
                ]
            );
        }
        return true;
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
        if ( (int) get_option( 'craf_appna_events_schema', 0 ) !== self::SCHEMA_VERSION ) {
            return [];
        }
        global $wpdb;
        $state      = [
            'insert_id'  => $wpdb->insert_id,
            'last_error' => $wpdb->last_error,
            'last_query' => $wpdb->last_query,
        ];
        $suppressed = $wpdb->suppress_errors( true );
        $rows       = [];
        try {
            $wpdb->last_error = '';
            $rows             = $wpdb->get_results( $wpdb->prepare( 'SELECT source_id,app_id,payload,storage_version FROM %i', self::bindings_table() ), ARRAY_A );
            if ( $wpdb->last_error ) {
                throw new \RuntimeException( 'Notification storage unavailable.' );
            }
        } catch ( \Throwable $error ) {
            // The next explicit owner setup can repair our tables; native hooks never migrate.
            delete_option( 'craf_appna_events_schema' );
            self::record_error( 'notification_storage_unavailable' );
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
    private static function binding_metadata( string $source ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT app_id,storage_version FROM %i WHERE source_id=%s', self::bindings_table(), $source ), ARRAY_A );
        return $row ? [
            'appId'            => $row['app_id'],
            '_storage_version' => (int) $row['storage_version'],
        ] : null;
    }

    /** One row per binding, fenced against a stale callback or dispatcher snapshot. */
    public static function save_binding( array $binding, ?array $previous = null ): bool {
        self::install();
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

    /** Reserve the resource sequence BEFORE bypassing cached role metadata. */
    public static function subject_snapshot( int $id, bool $with_version = true ): ?array {
        global $wpdb;
        $version = $with_version ? self::next_version( 'subject', $id ) : 0;
        if ( null === $version ) {
            return null;
        }
        $user = $wpdb->get_row( $wpdb->prepare( "SELECT ID,display_name,user_email FROM {$wpdb->users} WHERE ID=%d", $id ), ARRAY_A );
        if ( ! $user ) {
            return null;
        }
        $raw   = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s ORDER BY umeta_id LIMIT 1", $id, $wpdb->get_blog_prefix() . 'capabilities' ) );
        $caps  = maybe_unserialize( $raw );
        $roles = [];
        foreach ( is_array( $caps ) ? $caps : [] as $role => $enabled ) {
            if ( $enabled && wp_roles()->is_role( $role ) ) {
                $roles[] = (string) $role;
            }
        }
        return [
            'id'         => (string) $id,
            'version'    => (string) $version,
            'attributes' => [
                'roles' => $roles,
            ],
            'profile'    => [
                'displayName' => (string) $user['display_name'],
                'email'       => $user['user_email'] ?: null,
            ],
        ];
    }

    public static function definitions(): array {
        $types       = [
            'commerce.order.created',
            'commerce.order.submitted',
            'commerce.order.paid',
            'commerce.order.status_changed',
            'commerce.order.cancelled',
            'commerce.refund.created',
            'directory.listing.created',
            'directory.listing.submitted',
            'directory.listing.published',
            'directory.listing.status_changed',
            'directory.listing.deleted',
            'form.submitted',
        ];
        $definitions = [];
        foreach ( $types as $type ) {
            $definitions[] = [
                'type'          => $type,
                'fields'        => [
                    'entity' => 'object',
                ],
                'relationships' => [
                    strpos( $type, 'commerce.' ) === 0 ? 'customer' : ( strpos( $type, 'directory.' ) === 0 ? 'owner' : 'submitter' ),
                ],
                'stateful'      => in_array(
                    $type, [
                        'commerce.order.status_changed',
                        'directory.listing.status_changed',
                    ], true
                ),
            ];
        }
        return (array) apply_filters( 'craf_appna_notification_event_definitions', $definitions );
    }

    /** Called directly by successful WordPress/plugin hooks. Never sends HTTP. */
    public static function emit( string $type, array $data, array $context = [] ): ?string {
        global $wpdb;
        $state = [];
        foreach ( [
            'insert_id',
            'last_error',
            'last_query',
        ] as $name ) {
            $state[$name] = $wpdb->{$name} ?? null;
        }
        $suppressed = $wpdb->suppress_errors( true );
        try {
            if ( ! self::allowed() ) {
                return null;
            }
            $id           = isset( $context['id'] ) ? (string) $context['id'] : wp_generate_uuid4();
            $entity       = $context['entity'] ?? [
                'type' => 'custom',
                'id'   => $id,
            ];
            $entity['id'] = (string) $entity['id'];
            $event        = [
                'schemaVersion' => 1,
                'id'            => $id,
                'type'          => $type,
                'occurredAt'    => $context['occurredAt'] ?? gmdate( 'Y-m-d\TH:i:s\Z' ),
                'entity'        => $entity,
                'relationships' => empty( $context['relationships'] ) ? (object) [] : $context['relationships'],
                'data'          => empty( $data ) ? (object) [] : $data,
            ];
            $event        = wp_json_encode( $event );
            if ( ! is_string( $event ) ) {
                return null;
            }
            $channel = strpos( $type, 'system.' ) === 0 ? 'subjects' : 'events';
            $consent = get_option( 'craf_appna_notification_consent', [] );
            $queued  = false;
            foreach ( self::bindings() as $source => $binding ) {
                if ( empty( $binding['enabled'] ) ) {
                    continue;
                }
                self::queue( $source, $channel )->enqueue(
                    [
                        'event'      => $event,
                        'appId'      => $binding['appId'],
                        'generation' => $binding['generation'],
                        'epoch'      => $consent['epoch'],
                        'attempts'   => 0,
                        'expiresAt'  => $context['deliveryExpiresAt'] ?? time() + ( $channel === 'subjects' ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS ),
                        'check'      => $context['check'] ?? null,
                    ]
                );
                $queued = true;
            }
            return $queued ? $id : null;
        } catch ( \Throwable $error ) {
            self::record_error( 'enqueue_failed' );
            return null;
        } finally {
            $wpdb->suppress_errors( $suppressed );
            foreach ( $state as $name => $value ) {
                $wpdb->{$name} = $value;
            }
        }
    }

    public static function request( array $binding, string $path, array $value, string $scope = 'events', ?string $raw_body = null ) {
        $body      = $raw_body ?? wp_json_encode( $value );
        $timestamp = (string) time();
        $key       = $binding['keys'][$scope] ?? [];
        if ( empty( $key['id'] ) || empty( $key['secret'] ) ) {
            return new \WP_Error( 'missing_credential', __( 'Notification credential missing.', 'appnatively' ) );
        }
        return wp_safe_remote_post(
            $binding['endpoint'] . '/' . rawurlencode( $binding['sourceId'] ) . '/' . $path, [
                'timeout'     => 10,
                'redirection' => 0,
                'sslverify'   => true,
                'headers'     => [
                    'Content-Type'            => 'application/json',
                    'X-AppNatively-Key-Id'    => $key['id'],
                    'X-AppNatively-Timestamp' => $timestamp,
                    'X-AppNatively-Signature' => base64_encode( hash_hmac( 'sha256', $timestamp . '.' . $body, $key['secret'], true ) ),
                ],
                'body'        => $body,
            ]
        );
    }

    public static function dispatch(): void {
        if ( ! self::allowed() ) {
            return;
        }
        foreach ( self::bindings() as $source => $binding ) {
            foreach ( [
                'events',
                'subjects',
            ] as $channel ) {
                self::queue( $source, $channel )->dispatch();
            }
        }
    }

    public static function retry_blocked(): void {
        if ( ! self::allowed() ) {
            return;
        }
        foreach ( self::bindings() as $source => $binding ) {
            if ( empty( $binding['enabled'] ) ) {
                continue;
            }
            foreach ( [
                'events',
                'subjects',
            ] as $channel ) {
                self::queue( $source, $channel )->retry();
            }
        }
    }

    public static function revoke_session( int $user, string $ref, array $context = [] ): void {
        self::emit(
            'system.session.revoked', [
                'sessionRef' => $ref,
            ], array_merge(
                $context, [
                    'id'     => 'session.revoked:' . $user . ':' . $ref,
                    'entity' => [
                        'type' => 'user',
                        'id'   => (string) $user,
                    ],
                ]
            )
        );
    }

    public static function session_verifier_ref( string $verifier ): string {
        return hash_hmac( 'sha256', $verifier, wp_salt( 'auth' ) );
    }

    public static function session_ref( string $token ): string {
        if ( preg_match( '/^([1-9][0-9]*)\.([a-f0-9]{64})$/', $token, $parts ) ) {
            return self::app_session_ref( (int) $parts[1], hash( 'sha256', $parts[2] ) );
        }
        return self::session_verifier_ref( hash( 'sha256', $token ) );
    }

    public static function app_session_ref( int $user, string $hash ): string {
        return self::session_verifier_ref( 'app:' . $user . ':' . $hash );
    }

    /** Read existing credentials without invoking Auth::resolve's expired-token cleanup. */
    public static function bearer_user( \WP_REST_Request $request ): ?int {
        $token = Auth::get_token_from_request( $request );
        if ( ! $token || ! preg_match( '/^([1-9][0-9]*)\.([a-f0-9]{64})$/', $token, $parts ) ) {
            return null;
        }
        $tokens = get_user_meta( (int) $parts[1], Auth::META_KEY, true );
        $meta   = is_array( $tokens ) ? $tokens[hash( 'sha256', $parts[2] )] ?? null : null;
        return is_array( $meta ) && ( $meta['expires'] ?? 0 ) > time() && get_userdata( (int) $parts[1] ) ? (int) $parts[1] : null;
    }

    public static function session_metadata( \WP_REST_Request $request, int $user ): ?array {
        $token = Auth::get_token_from_request( $request );
        if ( $token ) {
            if ( self::bearer_user( $request ) !== $user ) {
                return null;
            }
            [, $secret] = explode( '.', $token, 2 );
            $meta       = get_user_meta( $user, Auth::META_KEY, true )[hash( 'sha256', $secret )] ?? null;
            return $meta ? [
                'sessionRef' => self::session_ref( $token ),
                'issuedAt'   => (int) $meta['issued'] * 1000,
                'expiresAt'  => (int) $meta['expires'] * 1000,
            ] : null;
        }
        $token = wp_get_session_token();
        $meta  = $token ? \WP_Session_Tokens::get_instance( $user )->get( $token ) : null;
        if ( ! $meta || ( $meta['expiration'] ?? 0 ) <= time() ) {
            return null;
        }
        return [
            'sessionRef' => self::session_ref( $token ),
            'issuedAt'   => (int) ( $meta['login'] ?? 0 ) * 1000,
            'expiresAt'  => (int) $meta['expiration'] * 1000,
        ];
    }
}

<?php

namespace Crafium\AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Models\DatabaseRow;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;

class NotificationController extends Controller
{
    private \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider $events;

    public function __construct( \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider $events ) {
        $this->events = $events;
    }

    public function routes(): void {
        // Studio owner key; never a mobile bearer token or an anonymous capability probe.
        $pair_route = [
            'methods'             => 'POST',
            'permission_callback' => [
                $this,
                'authorize_pairing'
            ],
            'callback'            => [
                $this,
                'provision'
            ]
        ];
        register_rest_route( 'craf_appna/v1', '/notifications/pair', $pair_route );
        register_rest_route(
            'craf_appna/v1', '/notifications/subjects', [
                'methods'             => 'POST',
                'permission_callback' => [
                    $this,
                    'authorize_subjects'
                ],
                'callback'            => [
                    $this,
                    'subjects'
                ]
            ]
        );
        register_rest_route(
            'craf_appna/v1', '/notifications/identity', [
                'methods'             => 'POST',
                'permission_callback' => [
                    $this,
                    'authorize_identity'
                ],
                'callback'            => [
                    $this,
                    'identity'
                ]
            ]
        );
    }

    public function authorize_pairing( \WP_REST_Request $request ) {
        return ( new \Crafium\AppNatively\App\Http\Middleware\EnsureIsConnectedApp() )->handle( $request, '__return_true' );
    }

    public function authorize_subjects( \WP_REST_Request $request ) {
        $source    = $request->get_header( 'X-AppNatively-Source-Id' );
        $binding   = Events::bindings()[$source] ?? null;
        $key       = $binding['keys']['subjects'] ?? [];
        $timestamp = $request->get_header( 'X-AppNatively-Timestamp' );
        if ( ! Events::allowed() || empty( $binding['enabled'] ) || ( $key['id'] ?? '' ) !== $request->get_header( 'X-AppNatively-Key-Id' ) || ! ctype_digit( (string) $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) {
            return new \WP_Error( 'invalid_signature', __( 'Source authentication required.', 'appnatively' ), ['status' => 401] );
        }
        $expected = base64_encode( hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $key['secret'], true ) );
        return hash_equals( $expected, (string) $request->get_header( 'X-AppNatively-Signature' ) );
    }

    public function subjects( \WP_REST_Request $request ) {
        $ids = $request->get_json_params()['ids'] ?? [];
        if ( ! is_array( $ids ) || count( $ids ) > 100 ) {
            return new \WP_Error( 'invalid_ids', __( 'Up to 100 user IDs are accepted.', 'appnatively' ), ['status' => 422] );
        }
        $subjects = [];
        try {
            foreach ( array_unique( array_filter( $ids, 'is_string' ) ) as $id ) {
                if ( ! is_string( $id ) || ! ctype_digit( $id ) ) {
                    continue;
                }
                $subject = Events::subject_snapshot( (int) $id );
                if ( ! $subject ) {
                    continue;
                }
                if ( ( $request->get_json_params()['profiles'] ?? false ) !== true ) {
                    unset( $subject['profile'] );
                }
                $subjects[] = $subject;
            }
        } catch ( \Crafium\AppNatively\App\Support\NotificationStorageException $error ) {
            return new \WP_Error( 'notification_storage_unavailable', __( 'Notification membership storage is temporarily unavailable.', 'appnatively' ), ['status' => 503] );
        }
        $data = [
            'subjects'         => $subjects,
            'membershipScheme' => 'subject-sequence-v1'
        ];
        // Role definitions contain no customer directory or membership assertions.
        if ( ( $request->get_json_params()['audienceFields'] ?? false ) === true ) {
            $roles = [];
            foreach ( array_slice( wp_roles()->get_names(), 0, 1000, true ) as $role => $label ) {
                $roles[] = [
                    'value' => (string) $role,
                    'label' => (string) translate_user_role( $label )
                ];
            }
            $data['audienceFields'] = [
                'version' => (string) (int) ( microtime( true ) * 1000000 ),
                'roles'   => $roles
            ];
        }
        $response = new \WP_REST_Response( $data );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        return $response;
    }

    public function authorize_identity( \WP_REST_Request $request ) {
        try {
            $bearer_user = Events::bearer_user( $request );
        } catch ( \Crafium\AppNatively\App\Support\AuthorizationStorageException $error ) {
            return new \WP_Error( 'craf_appna_auth_storage', $error->getMessage(), ['status' => 503] );
        }
        if ( $bearer_user ) {
            wp_set_current_user( $bearer_user );
        }
        if ( ! Events::allowed() || ! get_current_user_id() ) {
            return new \WP_Error( 'unauthorized', __( 'Authentication required.', 'appnatively' ), ['status' => 401] );
        }
        if ( ! $bearer_user && ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new \WP_Error( 'invalid_nonce', __( 'A valid REST nonce is required.', 'appnatively' ), ['status' => 403] );
        }
        return true;
    }

    /** Direct owner-authenticated pairing. The signed callback must finish before success. */
    public function provision( \WP_REST_Request $request ) {
        if ( ! Events::allowed() ) {
            return new \WP_Error( 'notifications_disabled', __( 'Enable Send notification events and the AppNatively API in WordPress settings first.', 'appnatively' ), ['status' => 403] );
        }
        Events::install();
        $value = $request->get_json_params();
        $valid = is_array( $value );
        foreach ( [
            'appId',
            'sourceId',
            'operationId'
        ] as $key ) {
            $valid = $valid && is_string( $value[$key] ?? null ) && preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', $value[$key] );
        }
        foreach ( [
            'connectionRevision',
            'generation'
        ] as $key ) {
            $valid = $valid && is_int( $value[$key] ?? null ) && $value[$key] > 0;
        }
        $valid    = $valid && is_string( $value['ticket'] ?? null ) && preg_match( '/^[a-f0-9]{64}$/', $value['ticket'] );
        $valid    = $valid && is_int( $value['expiresAt'] ?? null ) && $value['expiresAt'] > time() * 1000 && $value['expiresAt'] < ( time() + 1200 ) * 1000;
        $endpoint = $value['endpoint'] ?? null;
        $valid    = $valid && is_string( $endpoint ) && wp_http_validate_url( $endpoint ) && wp_parse_url( $endpoint, PHP_URL_SCHEME ) === 'https' && ! wp_parse_url( $endpoint, PHP_URL_QUERY ) && ! wp_parse_url( $endpoint, PHP_URL_FRAGMENT ) && preg_match( '#/notification-sources$#', $endpoint );
        foreach ( [
            'events',
            'identity',
            'subjects'
        ] as $scope ) {
            $valid = $valid && is_string( $value['keys'][$scope]['id'] ?? null ) && preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', $value['keys'][$scope]['id'] );
            $valid = $valid && is_string( $value['keys'][$scope]['secret'] ?? null ) && preg_match( '/^[a-f0-9]{64}$/', $value['keys'][$scope]['secret'] );
        }
        if ( ! $valid ) {
            return new \WP_Error( 'invalid_provisioning', __( 'Invalid event provisioning request.', 'appnatively' ), ['status' => 422] );
        }
        // Serialize revisions across all source identities of this app, with interruption recovery.
        global $wpdb;
        $lock = 'craf_appna_setup_' . hash( 'sha256', $value['appId'] );
        DatabaseRow::from_table( "options" )->where( "option_name",$lock )->where_raw( "CAST(option_value AS UNSIGNED)<%d",[time() - 120] )->delete();
        wp_cache_delete( $lock, 'options' );
        if ( ! add_option( $lock, (string) time(), '', false ) ) {
            return new \WP_Error( 'provisioning_busy', __( 'Event setup is in progress.', 'appnatively' ), ['status' => 503] );
        }
        try {
            $bindings = Events::bindings();
            foreach ( $bindings as $id => $binding ) {
                if ( ( $binding['appId'] ?? '' ) !== $value['appId'] ) {
                    continue;
                }
                if ( (int) ( $binding['connectionRevision'] ?? 0 ) > $value['connectionRevision'] ) {
                    return new \WP_Error( 'stale_connection', __( 'The website connection changed.', 'appnatively' ), ['status' => 409] );
                }
                if ( (int) ( $binding['connectionRevision'] ?? 0 ) === $value['connectionRevision'] && (int) ( $binding['expiresAt'] ?? 0 ) > $value['expiresAt'] ) {
                    return new \WP_Error( 'stale_operation', __( 'A newer setup operation is installed.', 'appnatively' ), ['status' => 409] );
                }
                if ( $id === $value['sourceId'] && hash_equals( (string) ( $binding['pairing_ticket_hash'] ?? hash( 'sha256', $binding['ticket'] ?? '' ) ), hash( 'sha256', $value['ticket'] ) ) ) {
                    if ( ( $binding['provisioning'] ?? '' ) !== 'ready' ) {
                        $paired = Events::pair_now( $value );
                        if ( is_wp_error( $paired ) ) {
                            return $paired;
                        }
                    }
                    $current  = Events::bindings()[$value['sourceId']] ?? $binding;
                    $response = new \WP_REST_Response(
                        [
                            'accepted' => true,
                            'status'   => $current['provisioning'] ?? 'failed'
                        ]
                    );
                    $response->header( 'Cache-Control', 'private, no-store' );
                    return $response;
                }
                // A credential repair is not a website switch. Keep its durable event queue.
                if ( $id === $value['sourceId'] && (int) ( $binding['connectionRevision'] ?? 0 ) === $value['connectionRevision'] && (int) ( $binding['generation'] ?? 0 ) === $value['generation'] ) {
                    continue;
                }
                $bindings[$id]['enabled'] = false;
                if ( ! Events::save_binding( $bindings[$id] ) ) {
                    return new \WP_Error( 'stale_binding', __( 'Event connection changed.', 'appnatively' ), ['status' => 409] );
                }
                foreach ( [
                    'events',
                    'subjects'
                ] as $channel ) {
                    Events::queue( $id, $channel )->discard();
                }
            }
            Events::install();
            $paired = Events::pair_now( $value );
            if ( is_wp_error( $paired ) ) {
                return $paired;
            }
            $current  = Events::bindings()[$value['sourceId']] ?? [];
            $response = new \WP_REST_Response(
                [
                    'accepted' => true,
                    'status'   => $current['provisioning'] ?? 'failed'
                ]
            );
            $response->header( 'Cache-Control', 'private, no-store' );
            return $response;
        } finally {
            delete_option( $lock );
        }
    }

    public function identity( \WP_REST_Request $request ) {
        $value = $request->get_json_params();
        if ( ! Events::allowed() || ! is_array( $value ) || ! is_string( $value['sourceId'] ?? null ) || ! is_string( $value['appId'] ?? null ) || ! is_int( $value['generation'] ?? null ) || ! is_string( $value['installationId'] ?? null ) || ! $value['installationId'] || strlen( $value['installationId'] ) > 200 || ! is_string( $value['challenge'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/', $value['challenge'] ) ) {
            return new \WP_Error( 'invalid_binding', __( 'Invalid notification identity binding.', 'appnatively' ), ['status' => 422] );
        }
        $binding = Events::bindings()[$value['sourceId']] ?? null;
        if ( ! $binding || empty( $binding['enabled'] ) || ( $binding['provisioning'] ?? '' ) !== 'ready' || $value['generation'] !== (int) $binding['generation'] || $value['appId'] !== $binding['appId'] ) {
            return new \WP_Error( 'invalid_binding', __( 'Invalid notification identity binding.', 'appnatively' ), ['status' => 422] );
        }
        try {
            $session = Events::session_metadata( $request, get_current_user_id() );
        } catch ( \Crafium\AppNatively\App\Support\AuthorizationStorageException $error ) {
            return new \WP_Error( 'craf_appna_auth_storage', $error->getMessage(), ['status' => 503] );
        }
        if ( ! $session || ! $session['issuedAt'] || $session['expiresAt'] <= time() * 1000 ) {
            return new \WP_Error( 'session_expired', __( 'Verify your login again.', 'appnatively' ), ['status' => 401] );
        }
        $identity = [
            'schemaVersion'  => 1,
            'sourceId'       => $binding['sourceId'],
            'generation'     => (int) $binding['generation'],
            'appId'          => $binding['appId'],
            'installationId' => sanitize_text_field( $value['installationId'] ?? '' ),
            'challenge'      => $value['challenge'],
            'externalUserId' => (string) get_current_user_id(),
            'sessionRef'     => $session['sessionRef'],
            'issuedAt'       => max( (int) ( microtime( true ) * 1000 ), $session['issuedAt'] ),
            'expiresAt'      => time() + 120
        ];
        $this->events->subject_updated( get_current_user_id() );
        $session_value = array_merge(
            $identity, [
                'sessionIssuedAt'  => $session['issuedAt'],
                'sessionExpiresAt' => $session['expiresAt']
            ]
        );
        unset( $session_value['expiresAt'] );
        $assertion         = wp_json_encode( $identity );
        $session_assertion = wp_json_encode( $session_value );
        $response          = new \WP_REST_Response(
            [
                'sessionProof' => [
                    'assertion' => $session_assertion,
                    'signature' => base64_encode( hash_hmac( 'sha256', $session_assertion, $binding['keys']['identity']['secret'], true ) )
                ],
                'assertion'    => $assertion,
                'signature'    => base64_encode( hash_hmac( 'sha256', $assertion, $binding['keys']['identity']['secret'], true ) ),
                'keyId'        => $binding['keys']['identity']['id']
            ]
        );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        return $response;
    }
}

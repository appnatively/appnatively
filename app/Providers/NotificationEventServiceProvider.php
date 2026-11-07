<?php

namespace Crafium\AppNatively\App\Providers;

defined( 'ABSPATH' ) || exit;
use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\App\Support\NotificationFormEvents as Forms;
use Crafium\AppNatively\WpMVC\Contracts\Provider;

class NotificationEventServiceProvider extends Provider
{
    public function register() {
    }

    private array $subjects = [];

    private array $old_sessions = [];

    private array $deleted_sessions = [];

    public function boot() {
        Events::boot_queues();
        add_action(
            'rest_api_init', [
                $this,
                'routes',
            ]
        );
        add_action(
            'admin_menu', [
                $this,
                'admin_menu',
            ]
        );
        add_action(
            'wp_enqueue_scripts', [
                $this,
                'website_bridge',
            ]
        );
        $this->register_event_hooks();
        $this->register_session_hooks();
        $this->register_form_hooks();
        $this->register_cli_commands();
    }

    private function register_event_hooks(): void {
        foreach ( [
            'woocommerce_new_order'                          => [
                'order_created',
                1,
            ],
            'woocommerce_checkout_order_processed'           => [
                'order_submitted',
                1,
            ],
            'woocommerce_store_api_checkout_order_processed' => [
                'order_submitted',
                1,
            ],
            'woocommerce_payment_complete'                   => [
                'order_paid',
                1,
            ],
            'woocommerce_order_status_changed'               => [
                'order_status',
                4,
            ],
            'woocommerce_order_refunded'                     => [
                'order_refunded',
                2,
            ],
            'transition_post_status'                         => [
                'listing_status',
                3,
            ],
            'deleted_post'                                   => [
                'listing_deleted',
                2,
            ],
            'set_user_role'                                  => [
                'subject_updated',
                1,
            ],
            'add_user_role'                                  => [
                'subject_updated',
                1,
            ],
            'remove_user_role'                               => [
                'subject_updated',
                1,
            ],
            'profile_update'                                 => [
                'subject_updated',
                1,
            ],
            'deleted_user'                                   => [
                'subject_deleted',
                1,
            ],
            'craf_appna_auth_tokens_changed'                 => [
                'app_tokens_changed',
                3,
            ],
        ] as $hook => [$method, $arguments] ) {
            add_action(
                $hook, [
                    $this,
                    'on_' . $method,
                ], 40, $arguments
            );
        }
    }

    private function register_session_hooks(): void {
        // Snapshot only; always preserve WordPress's metadata filter result.
        add_filter(
            'update_user_metadata', [
                $this,
                'snapshot_user_sessions',
            ], 40, 4
        );
        add_action(
            'updated_user_meta', [
                $this,
                'user_sessions_updated',
            ], 40, 4
        );
        add_action(
            'delete_user_meta', [
                $this,
                'snapshot_deleted_user_sessions',
            ], 40, 3
        );
        add_action(
            'deleted_user_meta', [
                $this,
                'user_sessions_deleted',
            ], 40, 3
        );
    }

    private function register_form_hooks(): void {
        // These hooks are emitted by both the integrated plugins and app submission adapters.
        add_action(
            'fluentform/submission_inserted', [
                $this,
                'fluentform_submitted',
            ], 40, 3
        );
        add_action(
            'frm_after_create_entry', [
                $this,
                'formidable_submitted',
            ], 40, 2
        );
        add_action(
            'weforms_entry_submission', [
                $this,
                'weforms_submitted',
            ], 40, 2
        );
        add_action(
            'formgent_after_create_form_response', [
                $this,
                'formgent_submitted',
            ], 40, 2
        );
        add_action(
            'forminator_after_handle_form', [
                $this,
                'forminator_submitted',
            ], 40, 1
        );
        add_action(
            'wpcf7_submit', [
                $this,
                'contact_form_7_submitted',
            ], 40, 2
        );
        add_action(
            'wpforms_process_complete', [
                $this,
                'wpforms_submitted',
            ], 40, 4
        );
        add_action(
            'ninja_forms_save_sub', [
                $this,
                'ninja_forms_submitted',
            ], 40, 1
        );
        add_action(
            'everest_forms_process_complete', [
                $this,
                'everest_forms_submitted',
            ], 40, 4
        );
        add_action(
            'srfm_form_submit', [
                $this,
                'sureforms_submitted',
            ], 40, 1
        );
        add_action(
            'happyforms_submission_success', [
                $this,
                'happyforms_submitted',
            ], 40, 2
        );
        add_action(
            'gutena_forms_submission', [
                $this,
                'gutena_forms_submitted',
            ], 40, 1
        );
        add_filter(
            'bitform_filter_return_submit_success', [
                $this,
                'bitform_submitted',
            ], 40, 2
        );
    }

    private function register_cli_commands(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command(
                'appnatively notifications dispatch', [
                    Events::class,
                    'dispatch',
                ]
            );
        }
    }

    private function run_notification_hook( string $method, array $args ): void {
        try {
            if ( Events::allowed() ) {
                $this->{$method}( ...$args );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'notification_hook_failed' );
        }
    }

    public function on_order_created( ...$args ): void {
        $this->run_notification_hook( 'order_created', $args );
    }

    public function on_order_submitted( ...$args ): void {
        $this->run_notification_hook( 'order_submitted', $args );
    }

    public function on_order_paid( ...$args ): void {
        $this->run_notification_hook( 'order_paid', $args );
    }

    public function on_order_status( ...$args ): void {
        $this->run_notification_hook( 'order_status', $args );
    }

    public function on_order_refunded( ...$args ): void {
        $this->run_notification_hook( 'order_refunded', $args );
    }

    public function on_listing_status( ...$args ): void {
        $this->run_notification_hook( 'listing_status', $args );
    }

    public function on_listing_deleted( ...$args ): void {
        $this->run_notification_hook( 'listing_deleted', $args );
    }

    public function on_subject_updated( ...$args ): void {
        $this->run_notification_hook( 'subject_updated', $args );
    }

    public function on_subject_deleted( ...$args ): void {
        $this->run_notification_hook( 'subject_deleted', $args );
    }

    public function on_app_tokens_changed( ...$args ): void {
        $this->run_notification_hook( 'app_tokens_changed', $args );
    }

    public function snapshot_user_sessions( $check, $user, $key, $value ) {
        try {
            if ( $key === 'session_tokens' && Events::allowed() ) {
                $this->old_sessions[$user] = (array) get_user_meta( $user, $key, true );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'session_read_failed' );
        }
        return $check;
    }

    public function user_sessions_updated( $id, $user, $key, $value ) {
        try {
            if ( $key !== 'session_tokens' || ! is_array( $value ) || ! Events::allowed() ) {
                return;
            }
            $this->removed_sessions( (int) $user, $this->old_sessions[$user] ?? [], $value, 'session_tokens' );
            unset( $this->old_sessions[$user] );
        } catch ( \Throwable $error ) {
            Events::record_error( 'session_hook_failed' );
        }
    }

    public function snapshot_deleted_user_sessions( $ids, $user, $key ) {
        try {
            if ( $key !== 'session_tokens' || ! Events::allowed() ) {
                return;
            }
            global $wpdb;
            foreach ( (array) $ids as $id ) {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT user_id,meta_value FROM {$wpdb->usermeta} WHERE umeta_id=%d", $id ), ARRAY_A );
                if ( $row ) {
                    $this->deleted_sessions[$id] = $row;
                }
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'session_read_failed' );
        }
    }

    public function user_sessions_deleted( $ids, $user, $key ) {
        try {
            if ( $key !== 'session_tokens' || ! Events::allowed() ) {
                return;
            }
            foreach ( (array) $ids as $id ) {
                $old = $this->deleted_sessions[$id] ?? null;
                unset( $this->deleted_sessions[$id] );
                if ( ! $old ) {
                    continue;
                }
                $user     = (int) $old['user_id'];
                $previous = maybe_unserialize( $old['meta_value'] );
                $this->removed_sessions( $user, is_array( $previous ) ? $previous : [], (array) get_user_meta( $user, $key, true ), $key );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'session_hook_failed' );
        }
    }

    public function fluentform_submitted( $id, $data, $form ) {
        try {
            $entry = class_exists( '\FluentForm\App\Models\Submission' ) ? \FluentForm\App\Models\Submission::find( $id ) : null;
            if ( ! $entry || ! in_array(
                $entry->status, [
                    'unread',
                    'read',
                ], true
            ) ) {
                return;
            }
            Forms::accepted( 'fluentform', (string) $entry->form_id, (string) $id, isset( $entry->user_id ) ? (int) $entry->user_id : null );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function formidable_submitted( $id, $form ) {
        try {
            $entry = class_exists( '\FrmEntry' ) ? \FrmEntry::getOne( $id ) : null;
            if ( ! $entry || ! empty( $entry->is_draft ) ) {
                return;
            }
            Forms::accepted( 'formidable', (string) $form, (string) $id, isset( $entry->user_id ) ? (int) $entry->user_id : null );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function weforms_submitted( $id, $form ) {
        try {
            Forms::accepted( 'weforms', (string) $form, (string) $id );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function formgent_submitted( $id, $form ) {
        try {
            if ( ! is_numeric( $id ) || (int) $id <= 0 || empty( $form->ID ) ) {
                return;
            }
            Forms::accepted( 'formgent', (string) $form->ID, (string) $id );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function forminator_submitted( $entry ) {
        try {
            if ( empty( $entry->is_spam ) && ( $entry->status ?? 'active' ) === 'active' ) {
                Forms::accepted( 'forminator', (string) $entry->form_id, (string) ( $entry->entry_id ?? '' ), isset( $entry->meta_data['_user_id']['value'] ) ? (int) $entry->meta_data['_user_id']['value'] : null );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function contact_form_7_submitted( $form, $result ) {
        try {
            if ( in_array(
                $result['status'] ?? '', [
                    'mail_sent',
                ], true
            ) ) {
                Forms::accepted( 'contact-form-7', (string) $form->id() );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function wpforms_submitted( $fields, $entry, $form, $id ) {
        try {
            Forms::accepted( 'wpforms', (string) ( $form['id'] ?? '' ), (string) $id );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function ninja_forms_submitted( $id ) {
        try {
            if ( ! $id || ! function_exists( 'Ninja_Forms' ) ) {
                return;
            }
            $sub = Ninja_Forms()->form()->sub( $id )->get();
            if ( $sub ) {
                $user = method_exists( $sub, 'get_user' ) ? $sub->get_user() : null;
                Forms::accepted( 'ninjaforms', (string) $sub->get_form_id(), (string) $id, $user ? (int) $user->ID : null );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function everest_forms_submitted( $fields, $entry, $form, $id ) {
        try {
            Forms::accepted( 'everest-forms', (string) ( $form['id'] ?? '' ), (string) $id, isset( $entry['user_id'] ) ? (int) $entry['user_id'] : null );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function sureforms_submitted( $result ) {
        try {
            if ( ( $result['success'] ?? false ) === true ) {
                Forms::accepted( 'sureforms', (string) ( $result['form_id'] ?? '' ), (string) ( $result['entry_id'] ?? '' ) );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function happyforms_submitted( $submission, $form ) {
        try {
            Forms::accepted( 'happyforms', (string) ( $form['ID'] ?? '' ) );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function gutena_forms_submitted( $submission ) {
        try {
            // Validation has accepted the submission; a separate email result cannot undo it.
            Forms::accepted( 'gutena-forms', (string) ( $submission['formID'] ?? '' ) );
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
    }

    public function bitform_submitted( $result, $form_id ) {
        try {
            // This filter runs after COMMIT, unlike bitform_after_save_entry_success.
            if ( ! empty( $result['entry_id'] ) ) {
                Forms::accepted( 'bitform', (string) $form_id, (string) $result['entry_id'] );
            }
        } catch ( \Throwable $error ) {
            Events::record_error( 'form_notification_failed' );
        }
        return $result;
    }

    private function emit_order( string $type, $value, string $suffix = '', array $extra = [] ): void {
        $order = is_object( $value ) ? $value : ( function_exists( 'wc_get_order' ) ? wc_get_order( $value ) : null );
        if ( ! $order || $order->get_status() === 'checkout-draft' ) {
            return;
        }
        $customer = (string) $order->get_customer_id();
        Events::emit(
            $type, [
                'entity' => array_merge(
                    [
                        'id'     => (string) $order->get_id(),
                        'number' => (string) $order->get_order_number(),
                        'status' => $order->get_status(),
                    ], $extra
                ),
            ], [
                'id'            => $type . ':' . $order->get_id() . ':' . $suffix,
                'entity'        => [
                    'type' => 'order',
                    'id'   => (string) $order->get_id(),
                ],
                'relationships' => $customer !== '0' ? [
                    'customer' => [
                        'externalUserId' => $customer,
                    ],
                ] : [],
            ]
        );
    }

    public function order_created( $id ): void {
        $this->emit_order( 'commerce.order.created', $id );
    }

    public function order_submitted( $order ): void {
        $this->emit_order( 'commerce.order.created', $order );
        $this->emit_order( 'commerce.order.submitted', $order );
    }

    public function order_paid( $id ): void {
        $this->emit_order( 'commerce.order.paid', $id );
    }

    public function order_status( $id, $from, $to, $order ): void {
        $version = wp_generate_uuid4();
        $this->emit_order(
            'commerce.order.status_changed', $order, (string) $version, [
                'from' => $from,
                'to'   => $to,
            ]
        );
        if ( $to === 'cancelled' ) {
            $this->emit_order( 'commerce.order.cancelled', $order, (string) $version );
        }
    }

    public function order_refunded( $id, $refund_id ): void {
        $this->emit_order(
            'commerce.refund.created', $id, (string) $refund_id, [
                'refundId' => (string) $refund_id,
            ]
        );
    }

    private function listing_integrations(): array {
        $types = [
            'hp_listing'    => 'hivepress',
            'at_biz_dir'    => 'directorist',
            'wpbdp_listing' => 'business-directory-plugin',
            'rtcl_listing'  => 'classified-listing',
            'gd_place'      => 'geodirectory',
        ];

        if ( function_exists( 'adqs_get_directory_post_types' ) ) {
            foreach ( (array) adqs_get_directory_post_types() as $post_type ) {
                $types[$post_type] = 'adirectory';
            }
        }

        if ( function_exists( 'geodir_get_posttypes' ) ) {
            foreach ( array_keys( (array) geodir_get_posttypes( 'array' ) ) as $post_type ) {
                $types[$post_type] = 'geodirectory';
            }
        }

        return (array) apply_filters( 'craf_appna_notification_listing_types', $types );
    }

    public function listing_status( $to, $from, $post ): void {
        $types = $this->listing_integrations();
        if ( ! isset( $types[$post->post_type] ) || $from === $to || wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
            return;
        }
        if ( in_array(
            $to, [
                'auto-draft',
                'inherit',
            ], true
        ) ) {
            return;
        }
        $version       = wp_generate_uuid4();
        $types_to_emit = [
            'directory.listing.status_changed',
        ];
        if ( in_array(
            $from, [
                'new',
                'auto-draft',
            ], true
        ) ) {
            $types_to_emit[] = 'directory.listing.created';
        }
        if ( $to === 'pending' ) {
            $types_to_emit[] = 'directory.listing.submitted';
        }
        if ( $to === 'publish' ) {
            $types_to_emit[] = 'directory.listing.published';
        }
        foreach ( $types_to_emit as $type ) {
            Events::emit(
                $type, [
                    'entity' => [
                        'id'          => (string) $post->ID,
                        'from'        => $from,
                        'to'          => $to,
                        'integration' => $types[$post->post_type],
                    ],
                ], [
                    'id'            => $type . ':' . $post->ID . ':' . $version,
                    'entity'        => [
                        'type' => 'listing',
                        'id'   => (string) $post->ID,
                    ],
                    'relationships' => [
                        'owner' => [
                            'externalUserId' => (string) $post->post_author,
                        ],
                    ],
                ]
            );
        }
    }

    public function listing_deleted( $id, $post ): void {
        if ( $post && isset( $this->listing_integrations()[$post->post_type] ) ) {
            Events::emit(
                'directory.listing.deleted', [
                    'entity' => [
                        'id' => (string) $id,
                    ],
                ], [
                    'id'            => 'listing.deleted:' . $id,
                    'entity'        => [
                        'type' => 'listing',
                        'id'   => (string) $id,
                    ],
                    'relationships' => [
                        'owner' => [
                            'externalUserId' => (string) $post->post_author,
                        ],
                    ],
                ]
            );
        }
    }

    public function subject_updated( $user_id ): void {
        $subject = Events::subject_snapshot( (int) $user_id, false );
        if ( ! $subject ) {
            return;
        }
        $hash = hash( 'sha256', wp_json_encode( $subject['attributes'] ) );
        if ( ( $this->subjects[$user_id] ?? '' ) === $hash ) {
            return;
        }
        if ( Events::emit(
            'system.subject.updated', [
                'attributes'       => $subject['attributes'],
                'membershipScheme' => 'subject-sequence-v1',
            ], [
                'entity' => [
                    'type' => 'user',
                    'id'   => (string) $user_id,
                ],
            ]
        ) ) {
            $this->subjects[$user_id] = $hash;
        }
    }

    public function subject_deleted( $user_id ): void {
        Events::emit(
            'system.subject.deleted', [], [
                'entity' => [
                    'type' => 'user',
                    'id'   => (string) $user_id,
                ],
            ]
        );
    }

    public function app_tokens_changed( int $user, array $previous, array $current ): void {
        $this->removed_sessions( $user, $previous, $current, Auth::META_KEY );
    }

    private function removed_sessions( int $user, array $previous, array $current, string $key ): void {
        foreach ( array_diff_key( $previous, $current ) as $hash => $meta ) {
            $expires = (int) ( $meta['expires'] ?? $meta['expiration'] ?? 0 );
            if ( $expires <= time() ) {
                continue;
            }
            $ref = $key === Auth::META_KEY ? Events::app_session_ref( $user, (string) $hash ) : Events::session_verifier_ref( (string) $hash );
            Events::revoke_session(
                $user, $ref, [
                    'deliveryExpiresAt' => $expires,
                    'check'             => [
                        'user' => $user,
                        'key'  => $key,
                        'hash' => (string) $hash,
                    ],
                ]
            );
        }
    }

    public function routes(): void {
        // Studio owner key; never a mobile bearer token or an anonymous capability probe.
        $pair_route = [
            'methods'             => 'POST',
            'permission_callback' => [
                $this,
                'authorize_pairing',
            ],
            'callback'            => [
                $this,
                'provision',
            ],
        ];
        register_rest_route( 'craf_appna/v1', '/notifications/pair', $pair_route );
        register_rest_route(
            'craf_appna/v1', '/notifications/subjects', [
                'methods'             => 'POST',
                'permission_callback' => [
                    $this,
                    'authorize_subjects',
                ],
                'callback'            => [
                    $this,
                    'subjects',
                ],
            ]
        );
        register_rest_route(
            'craf_appna/v1', '/notifications/identity', [
                'methods'             => 'POST',
                'permission_callback' => [
                    $this,
                    'authorize_identity',
                ],
                'callback'            => [
                    $this,
                    'identity',
                ],
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
            return new \WP_Error(
                'invalid_signature', __( 'Source authentication required.', 'appnatively' ), [
                    'status' => 401,
                ]
            );
        }
        $expected = base64_encode( hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $key['secret'], true ) );
        return hash_equals( $expected, (string) $request->get_header( 'X-AppNatively-Signature' ) );
    }

    public function subjects( \WP_REST_Request $request ) {
        $ids = $request->get_json_params()['ids'] ?? [];
        if ( ! is_array( $ids ) || count( $ids ) > 100 ) {
            return new \WP_Error(
                'invalid_ids', __( 'Up to 100 user IDs are accepted.', 'appnatively' ), [
                    'status' => 422,
                ]
            );
        }
        $subjects = [];
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
        $data = [
            'subjects'         => $subjects,
            'membershipScheme' => 'subject-sequence-v1',
        ];
        // Role definitions contain no customer directory or membership assertions.
        if ( ( $request->get_json_params()['audienceFields'] ?? false ) === true ) {
            $roles = [];
            foreach ( array_slice( wp_roles()->get_names(), 0, 1000, true ) as $role => $label ) {
                $roles[] = [
                    'value' => (string) $role,
                    'label' => (string) translate_user_role( $label ),
                ];
            }
            $data['audienceFields'] = [
                'version' => (string) (int) ( microtime( true ) * 1000000 ),
                'roles'   => $roles,
            ];
        }
        $response = new \WP_REST_Response( $data );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        return $response;
    }

    public function authorize_identity( \WP_REST_Request $request ) {
        $bearer_user = Events::bearer_user( $request );
        if ( $bearer_user ) {
            wp_set_current_user( $bearer_user );
        }
        if ( ! Events::allowed() || ! get_current_user_id() ) {
            return new \WP_Error(
                'unauthorized', __( 'Authentication required.', 'appnatively' ), [
                    'status' => 401,
                ]
            );
        }
        if ( ! $bearer_user && ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new \WP_Error(
                'invalid_nonce', __( 'A valid REST nonce is required.', 'appnatively' ), [
                    'status' => 403,
                ]
            );
        }
        return true;
    }

    /** Direct owner-authenticated pairing. The signed callback must finish before success. */
    public function provision( \WP_REST_Request $request ) {
        if ( ! Events::allowed() ) {
            return new \WP_Error(
                'notification_consent_required', __( 'Confirm notification data sharing in WordPress settings first.', 'appnatively' ), [
                    'status' => 403,
                ]
            );
        }
        Events::install();
        $value = $request->get_json_params();
        $valid = is_array( $value );
        foreach ( [
            'appId',
            'sourceId',
            'operationId',
        ] as $key ) {
            $valid = $valid && is_string( $value[$key] ?? null ) && preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', $value[$key] );
        }
        foreach ( [
            'connectionRevision',
            'generation',
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
            'subjects',
        ] as $scope ) {
            $valid = $valid && is_string( $value['keys'][$scope]['id'] ?? null ) && preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', $value['keys'][$scope]['id'] );
            $valid = $valid && is_string( $value['keys'][$scope]['secret'] ?? null ) && preg_match( '/^[a-f0-9]{64}$/', $value['keys'][$scope]['secret'] );
        }
        if ( ! $valid ) {
            return new \WP_Error(
                'invalid_provisioning', __( 'Invalid event provisioning request.', 'appnatively' ), [
                    'status' => 422,
                ]
            );
        }
        // Serialize revisions across all source identities of this app, with interruption recovery.
        global $wpdb;
        $lock = 'craf_appna_setup_' . hash( 'sha256', $value['appId'] );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND CAST(option_value AS UNSIGNED)<%d", $lock, time() - 120 ) );
        wp_cache_delete( $lock, 'options' );
        if ( ! add_option( $lock, (string) time(), '', false ) ) {
            return new \WP_Error(
                'provisioning_busy', __( 'Event setup is in progress.', 'appnatively' ), [
                    'status' => 503,
                ]
            );
        }
        try {
            $bindings = Events::bindings();
            foreach ( $bindings as $id => $binding ) {
                if ( ( $binding['appId'] ?? '' ) !== $value['appId'] ) {
                    continue;
                }
                if ( (int) ( $binding['connectionRevision'] ?? 0 ) > $value['connectionRevision'] ) {
                    return new \WP_Error(
                        'stale_connection', __( 'The website connection changed.', 'appnatively' ), [
                            'status' => 409,
                        ]
                    );
                }
                if ( (int) ( $binding['connectionRevision'] ?? 0 ) === $value['connectionRevision'] && (int) ( $binding['expiresAt'] ?? 0 ) > $value['expiresAt'] ) {
                    return new \WP_Error(
                        'stale_operation', __( 'A newer setup operation is installed.', 'appnatively' ), [
                            'status' => 409,
                        ]
                    );
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
                            'status'   => $current['provisioning'] ?? 'failed',
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
                    return new \WP_Error(
                        'stale_binding', __( 'Event connection changed.', 'appnatively' ), [
                            'status' => 409,
                        ]
                    );
                }
                foreach ( [
                    'events',
                    'subjects',
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
                    'status'   => $current['provisioning'] ?? 'failed',
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
            return new \WP_Error(
                'invalid_binding', __( 'Invalid notification identity binding.', 'appnatively' ), [
                    'status' => 422,
                ]
            );
        }
        $binding = Events::bindings()[$value['sourceId']] ?? null;
        if ( ! $binding || empty( $binding['enabled'] ) || ( $binding['provisioning'] ?? '' ) !== 'ready' || $value['generation'] !== (int) $binding['generation'] || $value['appId'] !== $binding['appId'] ) {
            return new \WP_Error(
                'invalid_binding', __( 'Invalid notification identity binding.', 'appnatively' ), [
                    'status' => 422,
                ]
            );
        }
        $session = Events::session_metadata( $request, get_current_user_id() );
        if ( ! $session || ! $session['issuedAt'] || $session['expiresAt'] <= time() * 1000 ) {
            return new \WP_Error(
                'session_expired', __( 'Verify your login again.', 'appnatively' ), [
                    'status' => 401,
                ]
            );
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
            'expiresAt'      => time() + 120,
        ];
        $this->subject_updated( get_current_user_id() );
        $session_value = array_merge(
            $identity, [
                'sessionIssuedAt'  => $session['issuedAt'],
                'sessionExpiresAt' => $session['expiresAt'],
            ]
        );
        unset( $session_value['expiresAt'] );
        $assertion         = wp_json_encode( $identity );
        $session_assertion = wp_json_encode( $session_value );
        $response          = new \WP_REST_Response(
            [
                'sessionProof' => [
                    'assertion' => $session_assertion,
                    'signature' => base64_encode( hash_hmac( 'sha256', $session_assertion, $binding['keys']['identity']['secret'], true ) ),
                ],
                'assertion'    => $assertion,
                'signature'    => base64_encode( hash_hmac( 'sha256', $assertion, $binding['keys']['identity']['secret'], true ) ),
                'keyId'        => $binding['keys']['identity']['id'],
            ]
        );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        return $response;
    }

    public function admin_menu(): void {
        add_options_page(
            __( 'AppNatively Notifications', 'appnatively' ), __( 'AppNatively Notifications', 'appnatively' ), 'manage_options', 'appnatively-events', [
                $this,
                'settings_page',
            ]
        );
    }

    public function settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( isset( $_POST['craf_appna_save_notifications'] ) && check_admin_referer( 'craf_appna_notifications' ) ) {
            $enabled  = isset( $_POST['craf_appna_notifications_enabled'] );
            $accepted = isset( $_POST['craf_appna_notification_consent'] );
            update_option( 'craf_appna_notifications_enabled', $enabled ? 'yes' : 'no', false );
            if ( $enabled && $accepted ) {
                Events::install();
                if ( (int) get_option( 'craf_appna_events_schema', 0 ) !== Events::SCHEMA_VERSION ) {
                    Events::record_error( 'notification_storage_unavailable' );
                    $accepted = false;
                }
                $consent = get_option( 'craf_appna_notification_consent', [] );
                if ( $accepted && ( ( $consent['version'] ?? 0 ) !== Events::CONSENT_VERSION || empty( $consent['epoch'] ) ) ) {
                    update_option(
                        'craf_appna_notification_consent', [
                            'version'    => Events::CONSENT_VERSION,
                            'acceptedBy' => get_current_user_id(),
                            'acceptedAt' => time(),
                            'epoch'      => wp_generate_uuid4(),
                        ], false
                    );
                }
            } else {
                delete_option( 'craf_appna_notification_consent' );
                Events::cancel_queues();
            }
        }
        if ( isset( $_POST['craf_appna_retry_events'] ) && check_admin_referer( 'craf_appna_retry_events' ) ) {
            Events::retry_blocked();
        }
        $consent = get_option( 'craf_appna_notification_consent', [] );
        echo '<div class="wrap"><h1>' . esc_html__( 'AppNatively Notifications', 'appnatively' ) . '</h1>';
        echo '<p>' . esc_html__( 'Choose whether to share event metadata with AppNatively for mobile notifications.', 'appnatively' ) . '</p>';
        echo '<p>' . esc_html__( 'After you confirm below and connect Studio, the plugin automatically sends order, listing and submission IDs/statuses, related user IDs, account roles and session revocations to your configured AppNatively service. Authenticated audience requests can also read names and email addresses. Built-in form events exclude submitted field values. Login tokens and passwords are never sent.', 'appnatively' ) . '</p>';
        echo '<p><a href="https://appnatively.com/privacy-policy" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy policy', 'appnatively' ) . '</a> · <a href="https://appnatively.com/terms-and-conditions" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Terms of service', 'appnatively' ) . '</a></p>';
        echo '<form method="post">';
        wp_nonce_field( 'craf_appna_notifications' );
        echo '<input type="hidden" name="craf_appna_save_notifications" value="1">';
        echo '<p><label><input type="checkbox" name="craf_appna_notifications_enabled" value="1" ' . checked( 'yes', get_option( 'craf_appna_notifications_enabled', 'yes' ), false ) . '> ' . esc_html__( 'Send notification events', 'appnatively' ) . '</label></p>';
        echo '<p><label><input type="checkbox" name="craf_appna_notification_consent" value="1" ' . checked( Events::CONSENT_VERSION, $consent['version'] ?? 0, false ) . '> ' . esc_html__( 'I authorize the event delivery and data sharing described above.', 'appnatively' ) . '</label></p>';
        submit_button( __( 'Save notification settings', 'appnatively' ) );
        echo '</form><p>' . esc_html__( 'Confirm sharing here before using Check Connection in Studio. Turning sending off cancels pending work; an already in-flight request can finish.', 'appnatively' ) . '</p>';
        echo '<h2>' . esc_html__( 'Delivery status', 'appnatively' ) . '</h2>';
        $error = get_option( 'craf_appna_event_last_error', [] );
        if ( is_array( $error ) && ! empty( $error['code'] ) ) {
            echo '<p>' . esc_html( $error['code'] ) . '</p>';
        }
        echo '<p>' . esc_html__( 'WpMVC Queue sends events in the background. Check WordPress cron, loopback access and the service connection if delivery is delayed. Repair the connection before retrying paused jobs. External event requests verify TLS and do not follow redirects.', 'appnatively' ) . '</p>';
        echo '<form method="post">';
        wp_nonce_field( 'craf_appna_retry_events' );
        echo '<input type="hidden" name="craf_appna_retry_events" value="1">';
        submit_button( __( 'Retry paused notification jobs', 'appnatively' ) );
        echo '</form><h2>' . esc_html__( 'Connected apps', 'appnatively' ) . '</h2><ul>';
        foreach ( Events::bindings() as $binding ) {
            echo '<li>' . esc_html( $binding['appId'] . ' — ' . $binding['sourceId'] . ' — ' . ( $binding['provisioning'] ?? 'failed' ) ) . '</li>';
        }
        echo '</ul></div>';
    }

    public function website_bridge(): void {
        if ( ! Events::allowed() ) {
            return;
        }
        $active = array_filter(
            Events::bindings(), [
                $this,
                'binding_is_ready',
            ]
        );
        if ( ! $active ) {
            return;
        }
        $handle = 'craf-appna-notifications';
        wp_register_script( $handle, plugins_url( 'resources/js/notification-bridge.js', dirname( __DIR__, 2 ) . '/appnatively.php' ), [], '1.0.0', true );
        wp_add_inline_script(
            $handle, 'window.crafAppnaNotifications = ' . wp_json_encode(
                [
                    'endpoint' => rest_url( 'craf_appna/v1/notifications/identity' ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                    'signedIn' => is_user_logged_in(),
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) . ';', 'before'
        );
        wp_enqueue_script( $handle );
    }

    private function binding_is_ready( $binding ) {
        return ! empty( $binding['enabled'] ) && ( $binding['provisioning'] ?? '' ) === 'ready';
    }
}

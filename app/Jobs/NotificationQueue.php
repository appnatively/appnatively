<?php

namespace Crafium\AppNatively\App\Jobs;

use Crafium\AppNatively\App\Models\DatabaseRow;

defined( 'ABSPATH' ) || exit;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
use Crafium\AppNatively\WpMVC\Queue\Sequence;
/** WpMVC owns persistence, locking, batching and recovery for each source/channel. */
class NotificationQueue extends Sequence
{
    protected $action = 'notifications';

    protected $allowed_batch_data_classes = false;

    protected $cron_interval = 1;

    private string $source;

    private string $channel;

    private array $item = [];

    private bool $kick_registered = false;

    public function __construct( string $source, string $channel ) {
        $this->source  = $source;
        $this->channel = $channel;
        $this->prefix  = 'craf_appna_nq_' . substr( hash( 'sha256', get_current_blog_id() . ':' . $source . ':' . $channel ), 0, 20 );
        parent::__construct();
    }

    public function enqueue( array $item ): void {
        $this->push_to_queue( $item )->save();
        // Only the queue's loopback is started at shutdown; cloud HTTP runs in its worker.
        if ( ! $this->kick_registered ) {
            $this->kick_registered = true;
            add_action(
                'shutdown', [$this, 'dispatch_at_shutdown']
            );
        }
    }

    public function dispatch_at_shutdown(): void {
        try {
            $this->dispatch();
        } catch ( \Throwable $error ) {
            Events::record_error( 'queue_dispatch_failed' );
        }
    }

    /** Keep framework batches out of WordPress's autoloaded front-end options. */
    public function save() {
        $data       = $this->data;
        $this->data = [];
        if ( ! $data ) {
            return $this;
        }
        $key   = $this->generate_key();
        $saved = is_multisite() ? update_site_option( $key, $data ) : update_option( $key, $data, false );
        if ( ! $saved ) {
            throw new \RuntimeException( 'Notification queue persistence failed.' );
        }
        return $this;
    }

    protected function get_item( $item ) {
        return $this->item;
    }

    /** A failed checkpoint must escape the task: the parent's batch is now stale. */
    protected function handle() {
        try {
            return parent::handle();
        } catch ( NotificationCheckpointException $error ) {
            Events::record_error( 'queue_checkpoint_failed' );
            $this->defer( 60 );
            $this->schedule_event();
            // Keep the process lock until its normal expiry; a conflicting writer may own it.
        }
    }

    /** Persist the exact prepared body before HTTP, while the framework holds its queue lock. */
    private function checkpoint( array $original ): void {
        try {
            global $wpdb;
            $batch = $this->get_batch();
            $index = array_search( $original, $batch->data, true );
            if ( false === $index ) {
                throw new NotificationCheckpointException( 'Notification batch changed.' );
            }
            $previous            = maybe_serialize( $batch->data );
            $batch->data[$index] = $this->item;
            if ( is_multisite() ) {
                $saved = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET meta_value=%s WHERE site_id=%d AND meta_key=%s AND BINARY meta_value=BINARY %s', $wpdb->sitemeta, maybe_serialize( $batch->data ), get_current_network_id(), $batch->key, $previous ) );
                wp_cache_delete( get_current_network_id() . ':' . $batch->key, 'site-options' );
            } else {
                $saved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET option_value=%s,autoload='no' WHERE option_name=%s AND BINARY option_value=BINARY %s", $wpdb->options, maybe_serialize( $batch->data ), $batch->key, $previous ) );
                wp_cache_delete( $batch->key, 'options' );
                wp_cache_delete( 'alloptions', 'options' );
            }
            if ( 1 !== $saved ) {
                throw new NotificationCheckpointException( 'Notification checkpoint unavailable or superseded.' );
            }
        } catch ( \Throwable $error ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Preserve the internal cause; never displayed to a website user.
            throw new NotificationCheckpointException( 'Notification checkpoint failed.', 0, $error );
        }
    }

    protected function perform_sequence_task( $item ) {
        $this->item = is_array( $item ) ? $item : [];
        if ( ! Events::allowed() || empty( $this->item['event'] ) ) {
            return false;
        }
        if ( ( $this->item['epoch'] ?? '' ) !== Events::delivery_epoch() || (int) ( $this->item['expiresAt'] ?? 0 ) <= time() ) {
            return false;
        }
        $binding = Events::bindings()[$this->source] ?? null;
        if ( (int) get_option( 'craf_appna_events_schema', 0 ) !== Events::SCHEMA_VERSION ) {
            return $this->defer( 60 );
        }
        if ( ! $binding || empty( $binding['enabled'] ) || ( $this->item['appId'] ?? '' ) !== $binding['appId'] || (int) ( $this->item['generation'] ?? 0 ) !== (int) $binding['generation'] ) {
            return false;
        }
        try {
            if ( ( $binding['provisioning'] ?? '' ) !== 'ready' ) {
                return $this->defer( 60 );
            }
            $check = $this->item['check'] ?? null;
            if ( is_array( $check ) ) {
                // A caller can roll back after a hook. Confirm removal against committed storage.
                global $wpdb;
                $wpdb->last_error = '';
                $raw              = DatabaseRow::value( DatabaseRow::from_table( "usermeta" )->where( "user_id",$check["user"] )->where( "meta_key",$check["key"] )->order_by( "umeta_id" ),"meta_value" );
                if ( $wpdb->last_error ) {
                    return $this->defer( 60 );
                }
                $current = is_string( $raw ) ? maybe_unserialize( $raw ) : [];
                if ( is_array( $current ) && isset( $current[$check['hash']] ) ) {
                    return false;
                }
            }
            $event = json_decode( $this->item['event'] );
            if ( ! is_object( $event ) || ! isset( $event->type, $event->entity->id, $event->entity->type ) ) {
                return false;
            }
            $prepare = false;
            if ( $event->type === 'system.subject.updated' && ! isset( $event->entity->version ) ) {
                $subject = Events::subject_snapshot( (int) $event->entity->id );
                if ( ! $subject ) {
                    return false;
                }
                $event->entity->version = $subject['version'];
                $event->data            = (object) [
                    'attributes'       => $subject['attributes'],
                    'membershipScheme' => 'subject-sequence-v1',
                ];
                // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- established JSON event field.
                $event->occurredAt = gmdate( 'Y-m-d\TH:i:s\Z' );
                $prepare           = true;
            } elseif ( in_array(
                $event->type, [
                    'commerce.order.status_changed',
                    'directory.listing.status_changed',
                ], true
            ) && ! isset( $event->entity->version ) ) {
                $version = Events::next_version( $event->entity->type, (int) $event->entity->id );
                if ( null === $version ) {
                    return $this->defer( 60 );
                }
                $event->entity->version = (string) $version;
                $prepare                = true;
            }
            if ( $prepare ) {
                $body = wp_json_encode( $event );
                if ( ! is_string( $body ) ) {
                    throw new \RuntimeException( 'Notification serialization failed.' );
                }
                $this->item['event'] = $body;
                $this->checkpoint( $item );
            }
            $body     = $this->item['event'];
            $response = Events::request( $binding, $this->channel, [], $this->channel, $body );
            $code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                delete_option( $this->identifier . '_retry_at' );
                return false;
            }
            ++$this->item['attempts'];
            Events::record_error( 'delivery_http_' . (int) $code );
            // Retain the item in WpMVC's paused queue for an explicit configuration repair.
            if ( in_array(
                $code, [
                    400,
                    401,
                    403,
                    409,
                    413,
                    422,
                ], true
            ) ) {
                return $this->defer( 0 );
            }
            return $this->defer( min( 3600, 30 * 2 ** min( 7, $this->item['attempts'] - 1 ) ) );
        } catch ( NotificationCheckpointException $error ) {
            throw $error;
        } catch ( \Throwable $error ) {
            Events::record_error( 'notification_worker_failed' );
            return $this->defer( 60 );
        }
    }

    private function defer( int $delay ): bool {
        update_option( $this->identifier . '_retry_at', $delay ? time() + $delay : 0, false );
        $this->pause();
        return true;
    }

    public function dispatch() {
        if ( ! Events::allowed() || $this->is_paused() ) {
            return false;
        }
        $response = parent::dispatch();
        if ( is_wp_error( $response ) ) {
            Events::record_error( 'queue_loopback_failed' );
        }
        return $response;
    }

    /** The upstream healthcheck exits PHP when idle/busy; other cron jobs must continue. */
    public function handle_cron_healthcheck() {
        if ( $this->is_processing() ) {
            return;
        }
        if ( ! Events::allowed() ) {
            $this->discard();
            return;
        }
        if ( $this->is_queue_empty() ) {
            $this->clear_scheduled_event();
            return;
        }
        $retry = get_option( $this->identifier . '_retry_at', 0 );
        $batch = $this->get_batch();
        $first = reset( $batch->data );
        if ( $this->is_paused() ) {
            if ( $retry && $retry <= time() || ( $first['expiresAt'] ?? 0 ) <= time() ) {
                $this->resume();
            }
            return;
        }
        $this->dispatch();
    }

    public function retry(): void {
        delete_option( $this->identifier . '_retry_at' );
        $this->resume();
    }

    public function discard(): void {
        $this->delete_all();
        $this->clear_scheduled_event();
        delete_option( $this->identifier . '_retry_at' );
    }

    protected function get_post_args() {
        $args                = parent::get_post_args();
        $args['sslverify']   = true;
        $args['redirection'] = 0;
        return $args;
    }

    protected function triggered_error( ?array $error ) {
        if ( $error && in_array(
            $error['type'], [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
            ], true
        ) ) {
            Events::record_error( 'notification_worker_fatal' );
        }
    }
}

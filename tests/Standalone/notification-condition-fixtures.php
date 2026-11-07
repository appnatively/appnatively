<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

/** Real provider adapters, synthetic objects, and an explicit event collector. No WordPress boot/network. */
namespace {
    if ( PHP_SAPI !== 'cli' ) {
        exit;
    }
}
namespace Crafium\AppNatively\WpMVC\Contracts {
    class Provider
    {
    }
}
namespace Crafium\AppNatively\App\Support {
    class NotificationEvents
    {
        public static $events = [];

        public static function boot_queues() {
        }

        public static function allowed() {
            return true;
        }

        public static function record_error( $code ) {
            throw new \RuntimeException( $code );
        }

        public static function emit( $type, $data, $context = [] ) {
            self::$events[] = [
                'type' => $type,
                'data' => $data,
            ];
            return $context['id'] ?? 'fixture-event';
        }

        public static function next_version( ...$args ) {
            return 1;
        }
    }
}
namespace {
    define( 'ABSPATH', '/unused/' );
    $hooks = [];

    function add_action( $name, $callback, ...$args ) {
        $GLOBALS['hooks'][$name][] = $callback;
    }

    function add_filter( ...$args ) {
    }

    function apply_filters( $name, $value ) {
        return $value;
    }

    function get_current_user_id() {
        return 7;
    }

    function wp_generate_uuid4() {
        return bin2hex( random_bytes( 16 ) );
    }

    function wp_is_post_revision( ...$args ) {
        return false;
    }

    function wp_is_post_autosave( ...$args ) {
        return false;
    }

    require dirname( __DIR__, 2 ) . '/app/Support/NotificationFormEvents.php';
    require dirname( __DIR__, 2 ) . '/app/Providers/NotificationEventServiceProvider.php';
    $provider = new \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
    $provider->boot();
    $order = new class
    {
        public function get_status() {
            return 'completed';
        }

        public function get_customer_id() {
            return 7;
        }

        public function get_id() {
            return 42;
        }

        public function get_order_number() {
            return '1042';
        }

        public function get_meta( ...$args ) {
            return 1;
        }

        public function update_meta_data( ...$args ) {
        }

        public function save_meta_data() {
        }
    };

    function wc_get_order( $id ) {
        return $GLOBALS['order'];
    }

    $provider->order_created( $order );
    $provider->order_submitted( $order );
    $provider->order_paid( $order );
    $provider->order_status( 42, 'processing', 'cancelled', $order );
    $provider->order_refunded( 42, 99 );
    $listing = (object) [
        'ID'          => 55,
        'post_type'   => 'hp_listing',
        'post_author' => 7,
    ];
    $provider->listing_status( 'pending', 'new', $listing );
    $provider->listing_status( 'publish', 'pending', $listing );
    $provider->listing_deleted( 55, $listing );
    $hooks['formgent_after_create_form_response'][0](
        72, (object) [
            'ID' => 123,
        ]
    );
    echo json_encode( \Crafium\AppNatively\App\Support\NotificationEvents::$events );
}

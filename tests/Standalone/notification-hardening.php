<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

namespace FluentForm\App\Models {
    class Submission
    {
        public static $entries = [];

        public static function find( $id ) {
            return self::$entries[$id] ?? null;
        }
    }
}
namespace {
    require __DIR__ . '/notification-fixture.php';
    use Crafium\AppNatively\App\Support\NotificationEvents as Events;
    use FluentForm\App\Models\Submission;
    $checks = 0;

    function check( $condition, $message ) {
        if ( ! $condition ) {
            throw new \RuntimeException( $message );
        }
        $GLOBALS['checks']++;
    }

    $binding = [
        'sourceId'     => 'source',
        'appId'        => 'app',
        'generation'   => 1,
        'enabled'      => true,
        'provisioning' => 'ready',
        'endpoint'     => 'https://cloud.example.test/api/notification-sources',
        'keys'         => [],
    ];
    foreach ( [
        'events',
        'identity',
        'subjects',
    ] as $scope ) {
        $binding['keys'][$scope] = [
            'id'     => $scope,
            'secret' => str_repeat( 's', 64 ),
        ];
    }
    Events::save_binding( $binding );
    $provider = new \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
    $provider->boot();
    check( isset( $hooks['deleted_user'] ) && ! isset( $hooks['wpmu_delete_user'] ), 'Account deletion listens only to the completed native hook, including multisite' );
    $fluent = $hooks['fluentform/submission_inserted'][0];
    foreach ( [
        'spam',
        'rejected',
        'unread',
        'read',
    ] as $id => $status ) {
        Submission::$entries[$id + 1] = (object) [
            'id'      => $id + 1,
            'form_id' => 141,
            'status'  => $status,
        ];
        $fluent(
            $id + 1, [], (object) [
                'id' => 141,
            ]
        );
    }
    $fluent(
        999, [], (object) [
            'id' => 141,
        ]
    );
    $rows = queued_items();
    check( count( $rows ) === 2, 'Only real, accepted Fluent Forms entries queue' );
    $gent = $hooks['formgent_after_create_form_response'][0];
    $gent(
        55, (object) [
            'ID' => 123,
        ]
    );
    $gent(
        0, (object) [
            'ID' => 123,
        ]
    );
    $rows = queued_items();
    check( end( $rows )['event']['data']['entity']['formId'] === '123', 'FormGent uses uppercase ID' );
    // Neither native order nor listing event callbacks may write back to source entities.
    $order = new class
    {
        function get_status() {
            return 'processing';
        }

        function get_customer_id() {
            return 7;
        }

        function get_id() {
            return 42;
        }

        function get_order_number() {
            return '42';
        }

        function get_meta( ...$args ) {
            throw new RuntimeException( 'Must not read experimental source version' );
        }

        function update_meta_data( ...$args ) {
            throw new RuntimeException( 'Must not mutate native order' );
        }

        function save_meta_data() {
            throw new RuntimeException( 'Must not save native order' );
        }
    };
    $provider->order_status( 42, 'pending', 'processing', $order );
    $rows = queued_items();
    check( end( $rows )['event']['type'] === 'commerce.order.status_changed', 'Order transition queues without native version markers' );
    update_user_meta(
        7, 'capabilities', [
            'customer' => true,
        ]
    );
    $provider->subject_updated( 7 );
    $provider->subject_updated( 7 );
    $provider->subject_updated( 7 );
    check( count( queued_items( 'source', 'subjects' ) ) === 1, 'Equivalent role callbacks coalesce' );
    $before = $wpdb->get_results( 'SELECT * FROM usermeta', ARRAY_A );
    $provider->routes();
    $provider->identity(
        new WP_REST_Request(
            [
                'sourceId'       => 'source',
                'appId'          => 'app',
                'generation'     => 1,
                'installationId' => 'device',
                'challenge'      => str_repeat( 'a', 64 ),
            ]
        )
    );
    check( $wpdb->get_results( 'SELECT * FROM usermeta', ARRAY_A ) === $before, 'Identity proof is read only' );
    echo "PASS: {$checks} hook hardening checks (offline)\n";
}

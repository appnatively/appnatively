<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

require __DIR__ . '/notification-fixture.php';
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
$checks = 0;

function check( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

Events::save_binding( fixture_binding() );
$q = Events::queue( 'source', 'events' );
check( $q instanceof Crafium\AppNatively\WpMVC\Queue\Sequence, 'Uses the actual bundled WpMVC queue' );
check( get_option( 'craf_appna_notifications_enabled', 'yes' ) === 'yes', 'Sending preference defaults to On' );
check( Events::delivery_epoch() === '', 'New installations need no saved delivery epoch' );
check(
    Events::emit(
        'custom.created', [], [
            'id' => 'default-on',
        ]
    ) === 'default-on' && $q->is_queued() && ! $requests, 'Default On queues events without a separate sharing acknowledgement'
);
run_queue();
check( ! $q->is_queued() && count( $requests ) === 1, 'Default On delivers without saved sharing settings' );
$requests = [];
update_option( 'craf_appna_notification_epoch', 'fixture-delivery' );
check( Events::delivery_epoch() === 'fixture-delivery', 'Queue fencing uses the delivery epoch' );
$wpdb->insert_id  = 123;
$wpdb->last_query = 'native';
$wpdb->last_error = 'native diagnostic';
check(
    Events::emit(
        'custom.created', [], [
            'id' => 'signed',
        ]
    ) === 'signed', 'Public emitter returns the queued event identity'
);
check( count( queued_items() ) === 1 && ! $requests && ! $loopbacks, 'Hook only persists a real WpMVC batch' );
check( $wpdb->insert_id === 123 && $wpdb->last_query === 'native' && $wpdb->last_error === 'native diagnostic', 'Enqueue preserves caller database diagnostics and insert ID' );
$raw_batch = $q->get_batches()[0];
check( is_string( $raw_batch->data[0]['event'] ), 'Queue persists JSON strings without serialized PHP objects' );
run_queue();
check( ! $q->is_queued() && count( $requests ) === 1, 'Actual framework handler delivers and deletes a completed batch' );
$args = $requests[0][1];
$time = $args['headers']['X-AppNatively-Timestamp'];
check( $args['headers']['X-AppNatively-Signature'] === base64_encode( hash_hmac( 'sha256', $time . '.' . $args['body'], str_repeat( 's', 64 ), true ) ), 'Signs the exact sent body' );
check( $args['redirection'] === 0 && $args['sslverify'] === true, 'External delivery refuses redirects and verifies TLS' );
check( json_decode( $args['body'] )->relationships instanceof stdClass && json_decode( $args['body'] )->data instanceof stdClass, 'Empty objects survive strict queue deserialization' );
Events::emit(
    'custom.created', [
        'object' => (object) [],
        'list'   => [],
    ], [
        'id' => 'json-types',
    ]
);
run_queue();
$types = json_decode( end( $requests )[1]['body'] )->data;
check( $types->object instanceof stdClass && is_array( $types->list ), 'Nested empty objects and lists retain their distinct wire types' );
$reply_code = 503;
Events::emit(
    'custom.created', [], [
        'id' => 'retry',
    ]
);
run_queue();
check( $q->is_paused() && count( queued_items() ) === 1, 'Transient HTTP failure retains original queue item' );
$retry = get_option( $q->get_identifier() . '_retry_at' );
check( $retry >= time() + 29, 'Retry uses bounded backoff' );
$before = count( $requests );
$q->handle_cron_healthcheck();
check( count( $requests ) === $before && $q->is_paused(), 'Healthcheck does not spin before retry is due' );
update_option( $q->get_identifier() . '_retry_at', time() - 1 );
$q->handle_cron_healthcheck();
check( ! $q->is_paused(), 'Real queue resumes when retry becomes due' );
$reply_code = 202;
run_queue();
check( ! $q->is_queued() && json_decode( end( $requests )[1]['body'], true )['id'] === 'retry', 'Retry preserves event identity' );
$reply_code = 401;
Events::emit(
    'custom.created', [], [
        'id' => 'blocked',
    ]
);
run_queue();
check( $q->is_paused() && get_option( $q->get_identifier() . '_retry_at' ) === 0, 'Configuration error waits for owner retry' );
$reply_code = 202;
$q->retry();
run_queue();
check( ! $q->is_queued(), 'Owner retry recovers retained job' );
$old            = fixture_binding( 'retired' );
$old['enabled'] = false;
Events::save_binding( $old );
Events::emit(
    'system.subject.updated', [
        'attributes'       => [
            'roles' => [],
        ],
        'membershipScheme' => 'subject-sequence-v1',
    ], [
        'entity' => [
            'type' => 'user',
            'id'   => '7',
        ],
    ]
);
check( ! queued_items( 'retired', 'subjects' ), 'Retired bindings receive no account/security work' );
run_queue( 'source', 'subjects' );
check( isset( json_decode( end( $requests )[1]['body'], true )['entity']['version'] ), 'Background worker assigns account sequence' );
Events::emit(
    'custom.created', [], [
        'id' => 'cancel',
    ]
);
update_option( 'craf_appna_notifications_enabled', 'no' );
$q->handle_cron_healthcheck();
check( ! $q->is_queued(), 'Sending Off discards old work' );
update_option( 'craf_appna_notifications_enabled', 'yes' );
Events::emit( 'custom.created', [], ['id' => 'old-epoch'] );
update_option( 'craf_appna_notification_epoch', 'after-off' );
$before = count( $requests );
run_queue();
check( ! $q->is_queued() && count( $requests ) === $before, 'Jobs from before Off are discarded after sending resumes' );
Events::emit( 'custom.created', [], ['id' => 'new-epoch'] );
run_queue();
check( json_decode( end( $requests )[1]['body'], true )['id'] === 'new-epoch', 'New work is delivered after sending resumes' );
$wpdb->fail = 'INSERT INTO options';
check(
    Events::emit(
        'custom.created', [], [
            'id' => 'failed-save',
        ]
    ) === null, 'Queue persistence failure returns without throwing into native flow'
);
$wpdb->fail = '';
check( ! $q->is_queued(), 'Failed queue write did not pretend to persist a job' );
$before = count( $requests );
$q->handle_cron_healthcheck();
check( count( $requests ) === $before, 'Idle healthcheck returns without terminating PHP' );
Events::emit( 'custom.created', [], [ 'id' => 'storage-repair' ] );
delete_option( 'craf_appna_events_schema' );
run_queue();
check( $q->is_paused() && count( queued_items() ) === 1, 'Unavailable plugin storage retains pending work for repair' );
update_option( 'craf_appna_events_schema', Events::SCHEMA_VERSION );
$q->retry();
run_queue();
check( ! $q->is_queued(), 'Storage repair permits retained work to complete' );
Events::emit( 'custom.created', [], [ 'deliveryExpiresAt' => time() - 1 ] );
delete_option( 'craf_appna_events_schema' );
run_queue();
check( ! $q->is_queued(), 'Expired jobs are removed even while plugin storage needs repair' );
update_option( 'craf_appna_events_schema', Events::SCHEMA_VERSION );
echo "PASS: {$checks} real WpMVC queue checks (offline)\n";

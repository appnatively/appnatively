<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only synthetic diagnostics.
// phpcs:disable WordPress.DB.RestrictedClasses -- Disposable SQLite fixture only.

require __DIR__ . '/notification-fixture.php';
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
use Crafium\AppNatively\App\Jobs\NotificationQueue;

$checks = 0;

function check( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

function subject_event( string $id, string $user = '7' ): void {
    Events::emit(
        'system.subject.updated', [], [
            'id'     => $id,
            'entity' => [
                'type' => 'user',
                'id'   => $user
            ]
        ]
    );
}

function work( NotificationQueue $queue ): void {
    $method = new ReflectionMethod( $queue, 'handle' );
    $method->setAccessible( true );
    $method->invoke( $queue );
}

function retry( NotificationQueue $queue ): void {
    // Simulate normal expiry of the worker lock, without waiting on wall-clock time.
    delete_site_transient( $queue->get_identifier() . '_process_lock' );
    $queue->retry();
}

Events::save_binding( fixture_binding() );
$q = Events::queue( 'source', 'subjects' );
subject_event( 'lost-reply' );
Events::revoke_session( 7, 'revoked-session' );
$on_http    = function ( $url, $args ) use ( $q ) {
    unset( $GLOBALS['on_http'] );
    check( $q->get_batches()[0]->data[0]['event'] === $args['body'], 'Prepared bytes are durable at HTTP entry, before provider acceptance' );
};
$reply_code = 503;
work( $q );
$body = $requests[0][1]['body'];
check( $q->is_paused() && count( queued_items( 'source', 'subjects' ) ) === 2, 'Lost reply retains subject and following revocation' );
check( $q->get_batches()[0]->data[0]['event'] === $body, 'Prepared bytes persisted before uncertain submission' );
update_user_meta( 7, 'capabilities', ['administrator' => true] );
$reply_code = 202;
$restarted  = new NotificationQueue( 'source', 'subjects' );
retry( $restarted );
work( $restarted );
check( $requests[1][1]['body'] === $body, 'Restart retries exactly the original bytes despite changed roles' );
check( json_decode( $requests[2][1]['body'] )->type === 'system.session.revoked' && ! $q->is_queued(), 'Following revocation is delivered after idempotent retry' );

class WorkerInterruption extends Error {
}
class InterruptedQueue extends NotificationQueue {
    protected function task( $item ) {
        parent::task( $item );
        throw new WorkerInterruption( 'Accepted, but process died before framework batch update.' );
    }
}
$requests = [];
subject_event( 'worker-crash' );
try {
    work( new InterruptedQueue( 'source', 'subjects' ) );
} catch ( WorkerInterruption $error ) {
    check( true, 'Simulated interruption happens after HTTP acceptance, before framework persistence' );
}
$body  = $requests[0][1]['body'];
$batch = $q->get_batches()[0];
check( $batch->data[0]['event'] === $body, 'Crash leaves the accepted prepared body durable' );
check( $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM options WHERE option_name=%s', $batch->key ) ) === 'no', 'Checkpoint remains non-autoloaded' );
$restarted = new NotificationQueue( 'source', 'subjects' );
retry( $restarted );
work( $restarted );
check( $requests[1][1]['body'] === $body && ! $q->is_queued(), 'Crash recovery replays the same body and completes' );

$requests = [];
subject_event( 'checkpoint-failure' );
$original   = $q->get_batches()[0]->data;
$wpdb->fail = 'AND BINARY option_value';
work( $q );
$wpdb->fail = '';
check( ! $requests && $q->get_batches()[0]->data === $original, 'Failed checkpoint sends nothing and preserves the original batch' );
check( $q->is_paused() && get_option( $q->get_identifier() . '_retry_at' ) >= time() + 59, 'Failed checkpoint schedules a bounded retry' );
retry( $q );
work( $q );
check( ! $q->is_queued() && count( $requests ) === 1, 'Checkpoint storage recovery completes retained work' );

$requests = [];
subject_event( 'checkpoint-conflict' );
$batch                            = $q->get_batches()[0];
$competing                        = $batch->data;
$competing[0]['competing_writer'] = true;
$before_query                     = function ( $sql ) use ( $batch, $competing, $wpdb ) {
    if ( strpos( $sql, 'AND BINARY option_value' ) !== false ) {
        unset( $GLOBALS['before_query'] );
        $wpdb->db->exec( $wpdb->prepare( 'UPDATE options SET option_value=%s WHERE option_name=%s', serialize( $competing ), $batch->key ) );
    }
};
retry( $q );
work( $q );
check( ! $requests && $q->get_batches()[0]->data === $competing, 'CAS conflict sends nothing and never overwrites the newer batch' );
$q->discard();
retry( $q );

subject_event( 'legacy-prepared' );
$legacy                 = $q->get_batches()[0]->data[0];
$event                  = json_decode( $legacy['event'] );
$event->entity->version = '700';
$event->data            = (object) [
    'attributes'       => (object) ['roles' => ['customer']],
    'membershipScheme' => 'subject-sequence-v1'
];
$legacy['event']        = wp_json_encode( $event );
$legacy['attempts']     = 1;
$q->discard();
retry( $q );
$q->push_to_queue( $legacy )->save();
$reply_code = 409;
work( $q );
check( end( $requests )[1]['body'] === $legacy['event'], 'Already-versioned legacy work retains its exact stored bytes' );
check( $q->is_paused() && get_option( $q->get_identifier() . '_retry_at' ) === 0 && $q->is_queued(), 'Existing legacy conflict stays visible for explicit recovery' );
$q->discard();
retry( $q );
$reply_code = 202;

$provider = new Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
foreach ( ['(entity_type,entity_id,version)', '`users`', '`usermeta`'] as $failure ) {
    $requests = [];
    subject_event( 'storage-failure-' . $checks );
    $wpdb->fail = $failure;
    work( $q );
    check( ! $requests && count( queued_items( 'source', 'subjects' ) ) === 1, 'Storage failure retains work without HTTP: ' . $failure );
    check( $q->is_paused() && get_option( $q->get_identifier() . '_retry_at' ) >= time() + 59, 'Storage failure defers for 60 seconds: ' . $failure );
    $response = $provider->subjects( new WP_REST_Request( ['ids' => ['7']] ) );
    check( $response instanceof WP_Error && $response->code === 'notification_storage_unavailable' && $response->data['status'] === 503, 'Lookup returns 503 rather than partial membership: ' . $failure );
    $provider->on_subject_updated( 7 );
    check( true, 'Native subject hook does not throw during storage failure' );
    $wpdb->fail = '';
    retry( $q );
    work( $q );
    check( ! $q->is_queued() && count( $requests ) >= 1, 'Storage recovery completes retained work: ' . $failure );
}
$requests = [];
subject_event( 'missing-user', '999' );
work( $q );
check( ! $q->is_queued() && ! $requests, 'Confirmed absent user is distinct from a failed lookup' );
echo "PASS: {$checks} notification durability and storage checks (offline)\n";
